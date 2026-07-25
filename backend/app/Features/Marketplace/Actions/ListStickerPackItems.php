<?php

namespace App\Features\Marketplace\Actions;

use App\Features\Marketplace\Models\MarketItem;
use App\Features\Marketplace\Models\StickerPackItem;
use App\Features\Marketplace\Services\MarketplaceService;
use Illuminate\Support\Collection;

final readonly class ListStickerPackItems
{
    public function __construct(private MarketplaceService $marketplace) {}

    /** @return Collection<int, StickerPackItem> */
    public function execute(MarketItem $item): Collection
    {
        return $this->marketplace->stickersForPack($item);
    }
}
