<?php

namespace App\Features\LiveShopping\Http\Resources;

use App\Features\LiveShopping\Models\LiveProductPin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LiveProductPin */
final class LiveProductPinResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'product_id' => $this->product_id,
            'pinned_by' => $this->pinned_by,
            'is_pinned' => (bool) $this->is_pinned,
            'flash_deal_price' => $this->flash_deal_price,
            'flash_deal_ends_at' => $this->flash_deal_ends_at?->toISOString(),
            'has_flash_deal' => $this->hasActiveFlashDeal(),
            'effective_price' => $this->effectivePrice(),
            'sort_order' => $this->sort_order,
            'product' => $this->whenLoaded('product', fn () => new StoreProductResource($this->product)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
