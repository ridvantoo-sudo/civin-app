<?php

namespace App\Features\Marketplace\Http\Resources;

use App\Features\Marketplace\Models\MarketPurchase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MarketPurchase */
final class MarketPurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'market_item_id' => $this->market_item_id,
            'coins' => $this->coins,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
            'item' => $this->whenLoaded('item', fn () => new MarketItemResource($this->item)),
        ];
    }
}
