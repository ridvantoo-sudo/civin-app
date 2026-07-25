<?php

namespace App\Features\Marketplace\Http\Resources;

use App\Features\Marketplace\Models\UserMarketItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UserMarketItem */
final class UserMarketItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'market_item_id' => $this->market_item_id,
            'purchase_id' => $this->purchase_id,
            'status' => $this->status,
            'is_equipped' => (bool) $this->is_equipped,
            'acquired_at' => $this->acquired_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'metadata' => $this->metadata,
            'item' => $this->whenLoaded('item', fn () => new MarketItemResource($this->item)),
        ];
    }
}
