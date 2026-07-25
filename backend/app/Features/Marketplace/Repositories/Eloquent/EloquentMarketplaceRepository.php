<?php

namespace App\Features\Marketplace\Repositories\Eloquent;

use App\Features\Marketplace\DTOs\PurchaseMarketItemData;
use App\Features\Marketplace\Models\MarketItem;
use App\Features\Marketplace\Models\MarketPurchase;
use App\Features\Marketplace\Models\UserMarketItem;
use App\Features\Marketplace\Repositories\Contracts\MarketplaceRepository;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\WalletTransaction;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EloquentMarketplaceRepository implements MarketplaceRepository
{
    public function __construct(private readonly WalletRepository $wallets) {}

    public function catalog(?string $type = null): Collection
    {
        $query = MarketItem::query()
            ->where('status', MarketItem::STATUS_ACTIVE)
            ->where(function ($builder): void {
                $builder->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($builder): void {
                $builder->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderBy('sort_order')
            ->orderBy('coin_price');

        if ($type !== null && $type !== '') {
            $query->where('type', $type);
        }

        return $query->get();
    }

    public function findActiveItem(string $marketItemId): ?MarketItem
    {
        return MarketItem::query()
            ->whereKey($marketItemId)
            ->where('status', MarketItem::STATUS_ACTIVE)
            ->first();
    }

    public function inventoryForUser(User $user): Collection
    {
        return UserMarketItem::query()
            ->where('user_id', $user->getKey())
            ->where('status', UserMarketItem::STATUS_OWNED)
            ->with('item')
            ->orderByDesc('is_equipped')
            ->orderByDesc('acquired_at')
            ->get();
    }

    public function purchasesForUser(User $user, int $perPage = 30): LengthAwarePaginator
    {
        return MarketPurchase::query()
            ->where('user_id', $user->getKey())
            ->with('item')
            ->orderByDesc('created_at')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function equippedForUser(User $user): Collection
    {
        return UserMarketItem::query()
            ->where('user_id', $user->getKey())
            ->where('status', UserMarketItem::STATUS_OWNED)
            ->where('is_equipped', true)
            ->with('item')
            ->get();
    }

    public function findInventoryItem(User $user, string $userMarketItemId): ?UserMarketItem
    {
        return UserMarketItem::query()
            ->whereKey($userMarketItemId)
            ->where('user_id', $user->getKey())
            ->with('item')
            ->first();
    }

    public function purchase(User $user, MarketItem $item, PurchaseMarketItemData $data): UserMarketItem
    {
        return DB::transaction(function () use ($user, $item, $data): UserMarketItem {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $lockedItem = MarketItem::query()->lockForUpdate()->findOrFail($item->getKey());

            if (! $lockedItem->isPurchasableNow()) {
                throw ValidationException::withMessages([
                    'market_item_id' => 'This marketplace item is no longer available.',
                ]);
            }

            if ($lockedItem->type === MarketItem::TYPE_SPECIAL_USER_ID) {
                $this->assertSpecialIdAvailable($lockedItem);
            }

            $wallet = $this->wallets->lockForUser($lockedUser);

            if ($wallet->coins_balance < $lockedItem->coin_price) {
                throw ValidationException::withMessages(['coins' => 'Insufficient coin balance.']);
            }

            $now = now();
            $expiresAt = $lockedItem->isPermanent()
                ? null
                : $now->copy()->addDays((int) $lockedItem->duration_days);

            $purchase = MarketPurchase::query()->create([
                'user_id' => $lockedUser->getKey(),
                'market_item_id' => $lockedItem->getKey(),
                'coins' => $lockedItem->coin_price,
                'metadata' => $data->metadata === null || $data->metadata === [] ? null : $data->metadata,
                'created_at' => $now,
            ]);

            $inventoryMetadata = [];
            if ($lockedItem->type === MarketItem::TYPE_SPECIAL_USER_ID) {
                $inventoryMetadata['previous_username'] = $lockedUser->username;
                $inventoryMetadata['special_value'] = $lockedItem->special_value;
            }

            $inventory = UserMarketItem::query()->create([
                'user_id' => $lockedUser->getKey(),
                'market_item_id' => $lockedItem->getKey(),
                'purchase_id' => $purchase->getKey(),
                'status' => UserMarketItem::STATUS_OWNED,
                'is_equipped' => false,
                'acquired_at' => $now,
                'expires_at' => $expiresAt,
                'metadata' => $inventoryMetadata === [] ? null : $inventoryMetadata,
            ]);

            if ($lockedItem->is_limited && $lockedItem->stock_remaining !== null) {
                if ($lockedItem->stock_remaining < 1) {
                    throw ValidationException::withMessages([
                        'market_item_id' => 'This limited item is sold out.',
                    ]);
                }
                $lockedItem->decrement('stock_remaining');
            }

            $wallet->decrement('coins_balance', $lockedItem->coin_price);

            WalletTransaction::query()->create([
                'user_id' => $lockedUser->getKey(),
                'type' => MarketPurchase::WALLET_TYPE_MARKETPLACE_PURCHASE,
                'amount' => -$lockedItem->coin_price,
                'currency' => WalletTransaction::CURRENCY_COINS,
                'reference_type' => $purchase->getMorphClass(),
                'reference_id' => $purchase->getKey(),
                'metadata' => [
                    'market_item_id' => $lockedItem->getKey(),
                    'market_item_type' => $lockedItem->type,
                    'market_item_name' => $lockedItem->name,
                ],
                'created_at' => $now,
            ]);

            return $inventory->fresh(['item', 'purchase']);
        });
    }

    public function equip(User $user, UserMarketItem $inventoryItem): UserMarketItem
    {
        return DB::transaction(function () use ($user, $inventoryItem): UserMarketItem {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $locked = UserMarketItem::query()
                ->lockForUpdate()
                ->with('item')
                ->findOrFail($inventoryItem->getKey());

            if ($locked->user_id !== $lockedUser->getKey()) {
                throw ValidationException::withMessages(['inventory' => 'Inventory item not found.']);
            }

            if (! $locked->isActive()) {
                throw ValidationException::withMessages(['inventory' => 'This item is no longer active.']);
            }

            $item = $locked->item;
            if ($item === null) {
                throw ValidationException::withMessages(['inventory' => 'Marketplace item is unavailable.']);
            }

            UserMarketItem::query()
                ->where('user_id', $lockedUser->getKey())
                ->where('is_equipped', true)
                ->whereHas('item', fn ($query) => $query->where('type', $item->type))
                ->whereKeyNot($locked->getKey())
                ->update(['is_equipped' => false]);

            if ($item->type === MarketItem::TYPE_SPECIAL_USER_ID) {
                $this->applySpecialUsername($lockedUser, $locked, $item);
            }

            $locked->forceFill(['is_equipped' => true])->save();

            return $locked->fresh(['item']);
        });
    }

    public function unequip(User $user, UserMarketItem $inventoryItem): UserMarketItem
    {
        return DB::transaction(function () use ($user, $inventoryItem): UserMarketItem {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $locked = UserMarketItem::query()
                ->lockForUpdate()
                ->with('item')
                ->findOrFail($inventoryItem->getKey());

            if ($locked->user_id !== $lockedUser->getKey()) {
                throw ValidationException::withMessages(['inventory' => 'Inventory item not found.']);
            }

            if ($locked->item?->type === MarketItem::TYPE_SPECIAL_USER_ID && $locked->is_equipped) {
                $this->restoreUsername($lockedUser, $locked);
            }

            $locked->forceFill(['is_equipped' => false])->save();

            return $locked->fresh(['item']);
        });
    }

    public function expiredOwnedItems(): Collection
    {
        return UserMarketItem::query()
            ->where('status', UserMarketItem::STATUS_OWNED)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->with(['item', 'user'])
            ->get();
    }

    public function expire(UserMarketItem $inventoryItem): UserMarketItem
    {
        return DB::transaction(function () use ($inventoryItem): UserMarketItem {
            $locked = UserMarketItem::query()
                ->with(['item', 'user'])
                ->lockForUpdate()
                ->find($inventoryItem->getKey());

            if ($locked === null) {
                $inventoryItem->status = UserMarketItem::STATUS_EXPIRED;
                $inventoryItem->is_equipped = false;

                return $inventoryItem;
            }

            if ($locked->item?->type === MarketItem::TYPE_SPECIAL_USER_ID && $locked->is_equipped && $locked->user !== null) {
                $this->restoreUsername($locked->user, $locked);
            }

            $locked->forceFill([
                'status' => UserMarketItem::STATUS_EXPIRED,
                'is_equipped' => false,
            ])->save();

            return $locked->fresh(['item']);
        });
    }

    private function assertSpecialIdAvailable(MarketItem $item): void
    {
        $value = trim((string) $item->special_value);
        if ($value === '') {
            throw ValidationException::withMessages([
                'market_item_id' => 'Special user ID is misconfigured.',
            ]);
        }

        $taken = User::query()->where('username', $value)->exists();
        if ($taken) {
            throw ValidationException::withMessages([
                'market_item_id' => 'This special user ID is already taken.',
            ]);
        }

        $owned = UserMarketItem::query()
            ->where('status', UserMarketItem::STATUS_OWNED)
            ->whereHas('item', function ($query) use ($value): void {
                $query->where('type', MarketItem::TYPE_SPECIAL_USER_ID)
                    ->where('special_value', $value);
            })
            ->exists();

        if ($owned) {
            throw ValidationException::withMessages([
                'market_item_id' => 'This special user ID has already been claimed.',
            ]);
        }
    }

    private function applySpecialUsername(User $user, UserMarketItem $inventory, MarketItem $item): void
    {
        $value = trim((string) $item->special_value);
        if ($value === '') {
            throw ValidationException::withMessages(['inventory' => 'Special user ID is unavailable.']);
        }

        $conflict = User::query()
            ->where('username', $value)
            ->whereKeyNot($user->getKey())
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'inventory' => 'This special user ID is already in use.',
            ]);
        }

        $metadata = $inventory->metadata ?? [];
        if (! isset($metadata['previous_username'])) {
            $metadata['previous_username'] = $user->username;
        }
        $metadata['special_value'] = $value;
        $inventory->metadata = $metadata;
        $inventory->save();

        $user->forceFill(['username' => $value])->save();
    }

    private function restoreUsername(User $user, UserMarketItem $inventory): void
    {
        $previous = $inventory->metadata['previous_username'] ?? null;
        if (! is_string($previous) || trim($previous) === '') {
            return;
        }

        $previous = trim($previous);
        $conflict = User::query()
            ->where('username', $previous)
            ->whereKeyNot($user->getKey())
            ->exists();

        if ($conflict) {
            $fallback = 'user_'.substr(str_replace('-', '', (string) $user->getKey()), 0, 12);
            $user->forceFill(['username' => $fallback])->save();

            return;
        }

        $user->forceFill(['username' => $previous])->save();
    }
}
