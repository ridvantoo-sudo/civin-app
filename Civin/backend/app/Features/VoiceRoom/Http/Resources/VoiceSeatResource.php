<?php

namespace App\Features\VoiceRoom\Http\Resources;

use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class VoiceSeatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seat_index' => $this->seat_index,
            'status' => $this->status,
            'is_muted' => (bool) $this->is_muted,
            'user' => new SocialUserResource($this->whenLoaded('user')),
        ];
    }
}
