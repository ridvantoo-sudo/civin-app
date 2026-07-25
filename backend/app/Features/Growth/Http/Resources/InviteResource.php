<?php

namespace App\Features\Growth\Http\Resources;

use App\Features\Growth\Models\Invite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invite */
final class InviteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'token' => $this->token,
            'channel' => $this->channel,
            'target' => $this->target,
            'status' => $this->status,
            'accepted_by' => $this->accepted_by,
            'accepted_at' => $this->accepted_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
