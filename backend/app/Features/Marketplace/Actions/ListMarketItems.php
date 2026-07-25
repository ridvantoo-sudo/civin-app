<?php

namespace App\Features\Marketplace\Actions;

use App\Features\Marketplace\Services\MarketplaceService;
use Illuminate\Support\Collection;

final readonly class ListMarketItems
{
    public function __construct(private MarketplaceService $marketplace) {}

    public function execute(?string $type = null): Collection
    {
        return $this->marketplace->catalog($type);
    }
}
