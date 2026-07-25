<?php

namespace App\Features\CoinSeller\Actions;

use App\Features\CoinSeller\DTOs\AllocateInventoryData;
use App\Features\CoinSeller\Models\CoinSeller;
use App\Features\CoinSeller\Services\CoinSellerService;
use App\Features\Users\Models\User;

final readonly class AllocateCoinSellerInventory
{
    public function __construct(private CoinSellerService $sellers) {}

    public function execute(CoinSeller $seller, User $actor, AllocateInventoryData $data): CoinSeller
    {
        return $this->sellers->allocateInventory($seller, $actor, $data);
    }
}
