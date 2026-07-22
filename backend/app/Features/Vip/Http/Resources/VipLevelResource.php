<?php

namespace App\Features\Vip\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Features\Vip\Models\VipLevel */
final class VipLevelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'level' => $this->level,
            'coin_price' => $this->coin_price,
            'duration_days' => $this->duration_days,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'privileges' => $this->privileges(),
        ];
    }
}
