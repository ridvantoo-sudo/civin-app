<?php

namespace App\Features\LiveShopping\Http\Resources;

use App\Features\LiveShopping\Models\ShopOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ShopOrder */
final class ShopOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'buyer_id' => $this->buyer_id,
            'seller_id' => $this->seller_id,
            'room_id' => $this->room_id,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'payment_provider' => $this->payment_provider,
            'total_coins' => $this->total_coins,
            'subtotal_coins' => $this->subtotal_coins ?? $this->total_coins,
            'discount_coins' => $this->discount_coins ?? 0,
            'coupon_code' => $this->coupon_code,
            'store_coupon_id' => $this->store_coupon_id,
            'shipping' => [
                'name' => $this->shipping_name,
                'phone' => $this->shipping_phone,
                'address_line' => $this->shipping_address_line,
                'city' => $this->shipping_city,
                'country' => $this->shipping_country,
                'postal_code' => $this->shipping_postal_code,
                'tracking_number' => $this->tracking_number,
            ],
            'paid_at' => $this->paid_at?->toISOString(),
            'shipped_at' => $this->shipped_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'items' => ShopOrderItemResource::collection($this->whenLoaded('items')),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
