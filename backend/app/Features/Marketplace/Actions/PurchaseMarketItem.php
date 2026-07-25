<?php

namespace App\Features\Marketplace\Actions;

use App\Features\Marketplace\DTOs\PurchaseMarketItemData;
use App\Features\Marketplace\Models\UserMarketItem;
use App\Features\Marketplace\Services\MarketplaceService;
use App\Features\Users\Models\User;

final readonly class PurchaseMarketItem
{
    public function __construct(private MarketplaceService $marketplace) {}

    public function execute(User $user, PurchaseMarketItemData $data): UserMarketItem
    {
        return $this->marketplace->purchase($user, $data);
    }
}
