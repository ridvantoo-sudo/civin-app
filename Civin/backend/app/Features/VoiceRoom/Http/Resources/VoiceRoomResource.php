<?php

namespace App\Features\VoiceRoom\Http\Resources;

use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class VoiceRoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail,
            'status' => $this->status,
            'seat_count' => $this->seat_count,
            'participant_count' => $this->participant_count,
            'host' => new SocialUserResource($this->whenLoaded('host')),
            'seats' => VoiceSeatResource::collection($this->whenLoaded('seats')),
            'started_at' => $this->started_at?->toISOString(),
            'ended_at' => $this->ended_at?->toISOString(),
        ];
    }
}
