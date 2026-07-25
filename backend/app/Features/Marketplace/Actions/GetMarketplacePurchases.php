<?php

namespace App\Features\Marketplace\Actions;

use App\Features\Marketplace\Services\MarketplaceService;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class GetMarketplacePurchases
{
    public function __construct(private MarketplaceService $marketplace) {}

    public function execute(User $user, int $perPage = 30): LengthAwarePaginator
    {
        return $this->marketplace->purchases($user, $perPage);
    }
}
