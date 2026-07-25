<?php

namespace App\Features\Growth\Services;

use App\Features\Growth\DTOs\CreatePromoCodeData;
use App\Features\Growth\DTOs\RedeemPromoData;
use App\Features\Growth\Events\PromoRedeemed;
use App\Features\Growth\Models\PromoCode;
use App\Features\Growth\Models\PromoRedemption;
use App\Features\Growth\Support\PromoRewardType;
use App\Features\Users\Models\User;
use App\Features\Wallet\Events\WalletUpdated;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final readonly class PromoCodeService
{
    private const REDEEM_RATE_KEY = 'growth-promo-redeem:%s';

    public function __construct(private WalletRepository $wallets) {}

    public function create(User $creator, CreatePromoCodeData $data): PromoCode
    {
        $this->ensureEligible($creator);

        if (! in_array($data->rewardType, PromoRewardType::all(), true)) {
            throw ValidationException::withMessages(['reward_type' => 'Invalid promo reward type.']);
        }

        if ($data->rewardValue < 1) {
            throw ValidationException::withMessages(['reward_value' => 'Reward value must be at least 1.']);
        }

        if ($data->rewardType === PromoRewardType::PERCENT_OFF && $data->rewardValue > 100) {
            throw ValidationException::withMessages(['reward_value' => 'Percent off cannot exceed 100.']);
        }

        $code = strtoupper(trim($data->code));
        if (PromoCode::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => 'This promo code already exists.']);
        }

        return PromoCode::query()->create([
            'code' => $code,
            'reward_type' => $data->rewardType,
            'reward_value' => $data->rewardValue,
            'max_redemptions' => $data->maxRedemptions,
            'per_user_limit' => max(1, $data->perUserLimit),
            'active' => true,
            'starts_at' => $data->startsAt,
            'ends_at' => $data->endsAt,
            'created_by' => $creator->getKey(),
            'description' => $data->description,
            'metadata' => $data->metadata,
        ]);
    }

    public function redeem(User $user, RedeemPromoData $data): PromoRedemption
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::REDEEM_RATE_KEY, $user->getKey()));

        $promo = PromoCode::query()->where('code', strtoupper(trim($data->code)))->first();
        if ($promo === null || ! $promo->isCurrentlyValid()) {
            throw ValidationException::withMessages(['code' => 'This promo code is invalid or expired.']);
        }

        $userRedemptions = PromoRedemption::query()
            ->where('promo_code_id', $promo->getKey())
            ->where('user_id', $user->getKey())
            ->count();

        if ($userRedemptions >= (int) $promo->per_user_limit) {
            throw ValidationException::withMessages(['code' => 'You have already redeemed this promo code.']);
        }

        $redemption = DB::transaction(function () use ($user, $promo): PromoRedemption {
            $locked = PromoCode::query()->lockForUpdate()->findOrFail($promo->getKey());
            if (! $locked->isCurrentlyValid()) {
                throw ValidationException::withMessages(['code' => 'This promo code is invalid or expired.']);
            }

            $coins = $locked->reward_type === PromoRewardType::COINS ? (int) $locked->reward_value : 0;
            $diamonds = $locked->reward_type === PromoRewardType::DIAMONDS ? (int) $locked->reward_value : 0;

            $redemption = PromoRedemption::query()->create([
                'promo_code_id' => $locked->getKey(),
                'user_id' => $user->getKey(),
                'reward_type' => $locked->reward_type,
                'reward_value' => $locked->reward_value,
                'coins_awarded' => $coins,
                'diamonds_awarded' => $diamonds,
                'metadata' => $locked->reward_type === PromoRewardType::PERCENT_OFF
                    ? ['percent_off' => $locked->reward_value]
                    : null,
            ]);

            $locked->increment('redemptions_count');

            if ($locked->awardsWalletCredit()) {
                WalletUpdated::dispatch($this->wallets->applyGrowthReward(
                    $user,
                    $redemption,
                    $coins,
                    $diamonds,
                    ['kind' => 'promo_redeem', 'code' => $locked->code],
                ));
            }

            return $redemption->load('promoCode');
        });

        PromoRedeemed::dispatch($redemption);

        return $redemption;
    }

    public function validate(string $code): PromoCode
    {
        $promo = PromoCode::query()->where('code', strtoupper(trim($code)))->first();
        if ($promo === null || ! $promo->isCurrentlyValid()) {
            throw ValidationException::withMessages(['code' => 'This promo code is invalid or expired.']);
        }

        return $promo;
    }

    public function mine(User $user, int $perPage = 20): LengthAwarePaginator
    {
        $this->ensureEligible($user);

        return PromoRedemption::query()
            ->where('user_id', $user->getKey())
            ->with('promoCode')
            ->latest()
            ->paginate(max(1, min($perPage, 100)));
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== 'active') {
            throw new AuthorizationException('Only active registered users can use promo codes.');
        }
    }

    private function enforceRateLimit(string $key): void
    {
        if (RateLimiter::tooManyAttempts($key, 15)) {
            throw ValidationException::withMessages(['code' => 'Too many promo attempts. Try again later.']);
        }

        RateLimiter::hit($key, 60);
    }
}
