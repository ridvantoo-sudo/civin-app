<?php

namespace App\Features\Growth\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Features\Growth\Models\PromoCode */
final class PromoCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'reward_type' => $this->reward_type,
            'reward_value' => $this->reward_value,
            'max_redemptions' => $this->max_redemptions,
            'redemptions_count' => $this->redemptions_count,
            'per_user_limit' => $this->per_user_limit,
            'active' => $this->active,
            'description' => $this->description,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
        ];
    }
}
