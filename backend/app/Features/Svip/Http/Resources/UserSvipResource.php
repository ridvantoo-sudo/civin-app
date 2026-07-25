<?php

namespace App\Features\Svip\Http\Resources;

use App\Features\Svip\Models\UserSvip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UserSvip */
final class UserSvipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $level = $this->relationLoaded('level') ? $this->level : null;

        return [
            'id' => $this->id,
            'is_svip' => $this->isActive(),
            'status' => $this->status,
            'started_at' => $this->started_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'level' => $level === null ? null : new SvipLevelResource($level),
            'privileges' => $level === null ? null : $level->privileges(),
        ];
    }
}
