<?php

namespace App\Features\LiveShopping\Http\Resources;

use App\Features\LiveShopping\Models\StoreCoupon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StoreCoupon */
final class StoreCouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seller_id' => $this->seller_id,
            'code' => $this->code,
            'type' => $this->type,
            'value' => $this->value,
            'min_order_coins' => $this->min_order_coins,
            'max_discount_coins' => $this->max_discount_coins,
            'max_redemptions' => $this->max_redemptions,
            'redemptions_count' => $this->redemptions_count,
            'per_user_limit' => $this->per_user_limit,
            'active' => (bool) $this->active,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'description' => $this->description,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
