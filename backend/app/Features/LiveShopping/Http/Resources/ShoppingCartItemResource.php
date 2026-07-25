<?php

namespace App\Features\LiveShopping\Http\Resources;

use App\Features\LiveShopping\Models\ShoppingCartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ShoppingCartItem */
final class ShoppingCartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'live_pin_id' => $this->live_pin_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'line_total' => $this->lineTotal(),
            'product' => $this->whenLoaded('product', fn () => new StoreProductResource($this->product)),
        ];
    }
}
