<?php

namespace App\Features\Marketplace\Http\Resources;

use App\Features\Marketplace\Models\MarketItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MarketItem */
final class MarketItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'icon_url' => $this->icon_url,
            'asset_url' => $this->asset_url,
            'coin_price' => $this->coin_price,
            'duration_days' => $this->duration_days,
            'is_limited' => (bool) $this->is_limited,
            'stock_total' => $this->stock_total,
            'stock_remaining' => $this->stock_remaining,
            'special_value' => $this->special_value,
            'membership_required' => $this->membership_required,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'is_purchasable' => $this->isPurchasableNow(),
            'metadata' => $this->metadata,
            'sticker_count' => $this->when(
                $this->type === MarketItem::TYPE_STICKER_PACK,
                fn (): int => $this->relationLoaded('stickers')
                    ? $this->stickers->count()
                    : $this->stickers()->count(),
            ),
            'stickers' => StickerPackItemResource::collection($this->whenLoaded('stickers')),
        ];
    }
}
