<?php

namespace App\Features\Vip\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Features\Vip\Models\UserVip */
final class UserVipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $level = $this->relationLoaded('level') ? $this->level : null;

        return [
            'id' => $this->id,
            'is_vip' => $this->isActive(),
            'status' => $this->status,
            'started_at' => $this->started_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'level' => $level === null ? null : new VipLevelResource($level),
            'privileges' => $level === null ? null : $level->privileges(),
        ];
    }
}
