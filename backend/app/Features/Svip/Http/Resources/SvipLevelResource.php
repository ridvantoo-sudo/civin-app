<?php

namespace App\Features\Svip\Http\Resources;

use App\Features\Svip\Models\SvipLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SvipLevel */
final class SvipLevelResource extends JsonResource
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
