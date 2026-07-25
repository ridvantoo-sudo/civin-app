<?php

namespace App\Features\Marketplace\Repositories\Contracts;

use App\Features\Marketplace\DTOs\PurchaseMarketItemData;
use App\Features\Marketplace\Models\MarketItem;
use App\Features\Marketplace\Models\UserMarketItem;
use App\Features\Users\Models\User;
use Illuminate\Support\Collection;

interface MarketplaceRepository
{
    public function catalog(?string $type = null): Collection;

    public function findActiveItem(string $marketItemId): ?MarketItem;

    public function inventoryForUser(User $user): Collection;

    public function purchasesForUser(User $user, int $perPage = 30);

    public function equippedForUser(User $user): Collection;

    public function findInventoryItem(User $user, string $userMarketItemId): ?UserMarketItem;

    public function purchase(User $user, MarketItem $item, PurchaseMarketItemData $data): UserMarketItem;

    public function equip(User $user, UserMarketItem $inventoryItem): UserMarketItem;

    public function unequip(User $user, UserMarketItem $inventoryItem): UserMarketItem;

    public function expiredOwnedItems(): Collection;

    public function expire(UserMarketItem $inventoryItem): UserMarketItem;
}
