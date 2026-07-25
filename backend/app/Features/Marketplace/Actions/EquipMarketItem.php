<?php

namespace App\Features\Marketplace\Actions;

use App\Features\Marketplace\DTOs\EquipMarketItemData;
use App\Features\Marketplace\Models\UserMarketItem;
use App\Features\Marketplace\Services\MarketplaceService;
use App\Features\Users\Models\User;

final readonly class EquipMarketItem
{
    public function __construct(private MarketplaceService $marketplace) {}

    public function execute(User $user, EquipMarketItemData $data): UserMarketItem
    {
        return $this->marketplace->equip($user, $data);
    }
}
