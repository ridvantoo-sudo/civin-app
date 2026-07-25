<?php

namespace App\Features\Growth\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Features\Growth\Models\ReferralCode */
final class ReferralCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'uses_count' => $this->uses_count,
            'max_uses' => $this->max_uses,
            'active' => $this->active,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
