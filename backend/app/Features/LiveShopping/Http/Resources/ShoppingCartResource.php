<?php

namespace App\Features\LiveShopping\Http\Resources;

use App\Features\LiveShopping\Models\ShoppingCart;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ShoppingCart */
final class ShoppingCartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'room_id' => $this->room_id,
            'item_count' => $this->itemCount(),
            'total_coins' => $this->totalCoins(),
            'items' => ShoppingCartItemResource::collection($this->whenLoaded('items')),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
