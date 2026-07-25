<?php

namespace App\Features\Growth\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Features\Growth\Models\PromoRedemption */
final class PromoRedemptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'promo_code_id' => $this->promo_code_id,
            'reward_type' => $this->reward_type,
            'reward_value' => $this->reward_value,
            'coins_awarded' => $this->coins_awarded,
            'diamonds_awarded' => $this->diamonds_awarded,
            'promo' => new PromoCodeResource($this->whenLoaded('promoCode')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
