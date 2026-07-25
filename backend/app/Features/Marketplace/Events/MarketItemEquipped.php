<?php

namespace App\Features\Marketplace\Events;

use App\Features\Marketplace\Models\UserMarketItem;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MarketItemEquipped
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly UserMarketItem $inventoryItem) {}
}
