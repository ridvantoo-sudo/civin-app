<?php

namespace App\Features\LiveShopping\Http\Resources;

use App\Features\LiveShopping\Models\StoreProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StoreProduct */
final class StoreProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seller_id' => $this->seller_id,
            'name' => $this->name,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'price_coins' => $this->price_coins,
            'stock' => $this->stock,
            'status' => $this->status,
            'is_offline_available' => (bool) $this->is_offline_available,
            'sort_order' => $this->sort_order,
            'is_purchasable' => $this->isPurchasable(),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
