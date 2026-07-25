<?php

namespace App\Features\Marketplace\Actions;

use App\Features\Marketplace\Services\MarketplaceService;
use App\Features\Users\Models\User;
use Illuminate\Support\Collection;

final readonly class GetMarketplaceInventory
{
    public function __construct(private MarketplaceService $marketplace) {}

    public function execute(User $user): Collection
    {
        return $this->marketplace->inventory($user);
    }
}
