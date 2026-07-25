<?php

namespace App\Features\LiveShopping\Services;

use App\Features\LiveShopping\DTOs\CreateStoreCouponData;
use App\Features\LiveShopping\Models\ShopOrder;
use App\Features\LiveShopping\Models\StoreCoupon;
use App\Features\LiveShopping\Models\StoreCouponRedemption;
use App\Features\LiveShopping\Support\StoreCouponType;
use App\Features\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

final readonly class StoreCouponService
{
    public function create(User $seller, CreateStoreCouponData $data): StoreCoupon
    {
        $this->ensureEligible($seller);
        $this->assertCouponPayload($data);

        $code = strtoupper(trim($data->code));
        if (StoreCoupon::query()->where('seller_id', $seller->getKey())->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => 'You already have a coupon with this code.']);
        }

        return StoreCoupon::query()->create([
            'seller_id' => $seller->getKey(),
            'code' => $code,
            'type' => $data->type,
            'value' => $data->value,
            'min_order_coins' => $data->minOrderCoins,
            'max_discount_coins' => $data->maxDiscountCoins,
            'max_redemptions' => $data->maxRedemptions,
            'per_user_limit' => max(1, $data->perUserLimit),
            'active' => true,
            'starts_at' => $data->startsAt,
            'ends_at' => $data->endsAt,
            'description' => $data->description,
            'metadata' => $data->metadata,
        ]);
    }

    public function listForSeller(User $seller, int $perPage = 20): LengthAwarePaginator
    {
        $this->ensureEligible($seller);

        return StoreCoupon::query()
            ->where('seller_id', $seller->getKey())
            ->orderByDesc('created_at')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function deactivate(User $seller, StoreCoupon $coupon): StoreCoupon
    {
        $this->ensureEligible($seller);
        if ($coupon->seller_id !== $seller->getKey()) {
            throw new AuthorizationException('You do not own this coupon.');
        }

        $coupon->forceFill(['active' => false])->save();

        return $coupon;
    }

    /**
     * Preview discount without consuming the coupon.
     *
     * @return array{coupon: StoreCoupon, discount_coins: int, payable_coins: int, subtotal_coins: int}
     */
    public function validateForBuyer(User $buyer, string $code, string $sellerId, int $subtotalCoins): array
    {
        $this->ensureEligible($buyer);
        $coupon = $this->findUsable($code, $sellerId, $buyer, $subtotalCoins);
        $discount = $this->computeDiscount($coupon, $subtotalCoins);

        return [
            'coupon' => $coupon,
            'discount_coins' => $discount,
            'payable_coins' => max(0, $subtotalCoins - $discount),
            'subtotal_coins' => $subtotalCoins,
        ];
    }

    /**
     * Lock, validate, and return discount inside an open DB transaction.
     *
     * @return array{coupon: StoreCoupon, discount_coins: int}|null
     */
    public function resolveForCheckout(
        User $buyer,
        ?string $code,
        string $sellerId,
        int $subtotalCoins,
    ): ?array {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $coupon = $this->findUsable($code, $sellerId, $buyer, $subtotalCoins, lock: true);
        $discount = $this->computeDiscount($coupon, $subtotalCoins);

        return ['coupon' => $coupon, 'discount_coins' => $discount];
    }

    public function recordRedemption(StoreCoupon $coupon, User $buyer, ShopOrder $order, int $discountCoins): void
    {
        StoreCouponRedemption::query()->create([
            'store_coupon_id' => $coupon->getKey(),
            'user_id' => $buyer->getKey(),
            'shop_order_id' => $order->getKey(),
            'discount_coins' => $discountCoins,
        ]);
        $coupon->increment('redemptions_count');
    }

    private function findUsable(
        string $code,
        string $sellerId,
        User $buyer,
        int $subtotalCoins,
        bool $lock = false,
    ): StoreCoupon {
        $normalized = strtoupper(trim($code));
        $query = StoreCoupon::query()
            ->where('seller_id', $sellerId)
            ->where('code', $normalized);

        if ($lock) {
            $query->lockForUpdate();
        }

        $coupon = $query->first();
        if ($coupon === null || ! $coupon->isCurrentlyValid()) {
            throw ValidationException::withMessages(['coupon_code' => 'This store coupon is invalid or expired.']);
        }

        if ($coupon->min_order_coins !== null && $subtotalCoins < $coupon->min_order_coins) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Order subtotal is below the coupon minimum.',
            ]);
        }

        $userUses = StoreCouponRedemption::query()
            ->where('store_coupon_id', $coupon->getKey())
            ->where('user_id', $buyer->getKey())
            ->count();

        if ($userUses >= (int) $coupon->per_user_limit) {
            throw ValidationException::withMessages([
                'coupon_code' => 'You have already used this coupon the maximum number of times.',
            ]);
        }

        return $coupon;
    }

    private function computeDiscount(StoreCoupon $coupon, int $subtotalCoins): int
    {
        $discount = match ($coupon->type) {
            StoreCouponType::PERCENT => (int) floor($subtotalCoins * ((int) $coupon->value) / 100),
            StoreCouponType::FIXED_COINS => (int) $coupon->value,
            default => 0,
        };

        if ($coupon->max_discount_coins !== null) {
            $discount = min($discount, (int) $coupon->max_discount_coins);
        }

        return max(0, min($discount, $subtotalCoins));
    }

    private function assertCouponPayload(CreateStoreCouponData $data): void
    {
        if (! in_array($data->type, StoreCouponType::all(), true)) {
            throw ValidationException::withMessages(['type' => 'Invalid coupon type.']);
        }
        if ($data->value < 1) {
            throw ValidationException::withMessages(['value' => 'Value must be at least 1.']);
        }
        $maxPercent = (int) config('live_shopping.coupons.max_percent', 100);
        if ($data->type === StoreCouponType::PERCENT && $data->value > $maxPercent) {
            throw ValidationException::withMessages(['value' => "Percent cannot exceed {$maxPercent}."]);
        }
        if (strlen(trim($data->code)) < 3) {
            throw ValidationException::withMessages(['code' => 'Code must be at least 3 characters.']);
        }
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== 'active') {
            throw new AuthorizationException('Only active registered users can use store coupons.');
        }
    }
}
