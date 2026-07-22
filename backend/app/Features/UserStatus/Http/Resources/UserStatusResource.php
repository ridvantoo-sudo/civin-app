<?php

namespace App\Features\UserStatus\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UserStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'is_online' => $this->is_online,
            'is_live' => $this->is_live,
            'last_seen_at' => $this->last_seen_at?->toISOString(),
            'live_started_at' => $this->live_started_at?->toISOString(),
        ];
    }
}
