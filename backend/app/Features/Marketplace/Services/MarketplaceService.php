<?php

namespace App\Features\Marketplace\Services;

use App\Features\Marketplace\DTOs\EquipMarketItemData;
use App\Features\Marketplace\DTOs\PurchaseMarketItemData;
use App\Features\Marketplace\Events\MarketItemEquipped;
use App\Features\Marketplace\Events\MarketItemPurchased;
use App\Features\Marketplace\Models\MarketItem;
use App\Features\Marketplace\Models\UserMarketItem;
use App\Features\Marketplace\Repositories\Contracts\MarketplaceRepository;
use App\Features\Profiles\Models\Profile;
use App\Features\Users\Models\User;
use App\Features\Wallet\Events\WalletUpdated;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final readonly class MarketplaceService
{
    private const PURCHASE_RATE_KEY = 'marketplace-purchase:%s';

    private const RATE_MAX = 20;

    private const RATE_DECAY_SECONDS = 60;

    public function __construct(
        private MarketplaceRepository $marketplace,
        private WalletRepository $wallets,
    ) {}

    public function catalog(?string $type = null): Collection
    {
        if ($type !== null && $type !== '' && ! in_array($type, MarketItem::types(), true)) {
            throw ValidationException::withMessages(['type' => 'Invalid marketplace item type.']);
        }

        return $this->marketplace->catalog($type);
    }

    public function inventory(User $user): Collection
    {
        $items = $this->marketplace->inventoryForUser($user);

        foreach ($items as $item) {
            if ($item instanceof UserMarketItem && $item->isExpired()) {
                $this->marketplace->expire($item);
            }
        }

        return $this->marketplace->inventoryForUser($user);
    }

    public function purchases(User $user, int $perPage = 30): LengthAwarePaginator
    {
        return $this->marketplace->purchasesForUser($user, $perPage);
    }

    public function equipped(User $user): Collection
    {
        return $this->marketplace->equippedForUser($user)
            ->filter(fn (UserMarketItem $item): bool => $item->isActive())
            ->values();
    }

    public function purchase(User $user, PurchaseMarketItemData $data): UserMarketItem
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::PURCHASE_RATE_KEY, $user->getKey()));

        $item = $this->marketplace->findActiveItem($data->marketItemId);
        if ($item === null || ! $item->isPurchasableNow()) {
            throw ValidationException::withMessages([
                'market_item_id' => 'The selected marketplace item is unavailable.',
            ]);
        }

        $this->ensureMembership($user, $item);

        $inventory = $this->marketplace->purchase($user, $item, $data);

        $shouldAutoEquip = match ($item->type) {
            MarketItem::TYPE_STICKER_PACK => (bool) config('marketplace.sticker_packs.auto_equip_on_purchase', false),
            MarketItem::TYPE_PROFILE_THEME => (bool) config('marketplace.profile_themes.auto_equip_on_purchase', true),
            default => true,
        };

        if ($shouldAutoEquip) {
            $inventory = $this->marketplace->equip($user, $inventory);
            MarketItemEquipped::dispatch($inventory);
        }

        MarketItemPurchased::dispatch($inventory);
        WalletUpdated::dispatch($this->wallets->findOrCreateForUser($user)->fresh());

        return $inventory->loadMissing(['item', 'purchase']);
    }

    /**
     * @return Collection<int, \App\Features\Marketplace\Models\StickerPackItem>
     */
    public function stickersForPack(MarketItem $item): Collection
    {
        if ($item->type !== MarketItem::TYPE_STICKER_PACK) {
            throw ValidationException::withMessages([
                'item' => 'Only sticker packs have sticker contents.',
            ]);
        }

        return $item->stickers()->get();
    }

    public function equip(User $user, EquipMarketItemData $data): UserMarketItem
    {
        $this->ensureEligible($user);

        $inventory = $this->marketplace->findInventoryItem($user, $data->userMarketItemId);
        if ($inventory === null) {
            throw ValidationException::withMessages(['user_market_item_id' => 'Inventory item not found.']);
        }

        if ($inventory->isExpired()) {
            $this->marketplace->expire($inventory);
            throw ValidationException::withMessages(['user_market_item_id' => 'This item has expired.']);
        }

        $equipped = $this->marketplace->equip($user, $inventory);
        MarketItemEquipped::dispatch($equipped);

        return $equipped;
    }

    public function unequip(User $user, EquipMarketItemData $data): UserMarketItem
    {
        $this->ensureEligible($user);

        $inventory = $this->marketplace->findInventoryItem($user, $data->userMarketItemId);
        if ($inventory === null) {
            throw ValidationException::withMessages(['user_market_item_id' => 'Inventory item not found.']);
        }

        return $this->marketplace->unequip($user, $inventory);
    }

    public function expireDueItems(): int
    {
        return (int) DB::transaction(function (): int {
            $expired = $this->marketplace->expiredOwnedItems();
            $count = 0;

            foreach ($expired as $item) {
                $this->marketplace->expire($item);
                $count++;
            }

            return $count;
        });
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== 'active') {
            throw new AuthorizationException('Only active registered users can use the marketplace.');
        }
    }

    private function ensureMembership(User $user, MarketItem $item): void
    {
        $required = $item->membership_required;
        if ($required === null || $required === '') {
            return;
        }

        $profile = Profile::query()->where('user_id', $user->getKey())->first();
        $ok = match ($required) {
            'vip' => (bool) ($profile?->is_vip || $profile?->is_svip),
            'svip' => (bool) $profile?->is_svip,
            default => false,
        };

        if (! $ok) {
            throw ValidationException::withMessages([
                'market_item_id' => 'This item requires '.strtoupper($required).' membership.',
            ]);
        }
    }

    private function enforceRateLimit(string $key): void
    {
        if (RateLimiter::tooManyAttempts($key, self::RATE_MAX)) {
            throw ValidationException::withMessages([
                'purchase' => 'You are purchasing marketplace items too quickly.',
            ]);
        }

        RateLimiter::hit($key, self::RATE_DECAY_SECONDS);
    }
}
