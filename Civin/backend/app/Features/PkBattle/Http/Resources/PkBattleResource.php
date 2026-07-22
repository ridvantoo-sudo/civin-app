<?php

namespace App\Features\PkBattle\Http\Resources;

use App\Features\LiveStreaming\Http\Resources\LiveRoomResource;
use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PkBattleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'duration_seconds' => $this->duration_seconds,
            'room_a_id' => $this->room_a_id,
            'room_b_id' => $this->room_b_id,
            'host_a_id' => $this->host_a_id,
            'host_b_id' => $this->host_b_id,
            'winner_id' => $this->winner_id,
            'room_a' => new LiveRoomResource($this->whenLoaded('roomA')),
            'room_b' => new LiveRoomResource($this->whenLoaded('roomB')),
            'host_a' => new SocialUserResource($this->whenLoaded('hostA')),
            'host_b' => new SocialUserResource($this->whenLoaded('hostB')),
            'winner' => new SocialUserResource($this->whenLoaded('winner')),
            'scores' => PkScoreResource::collection($this->whenLoaded('scores')),
            'rewards' => PkRewardResource::collection($this->whenLoaded('rewards')),
            'started_at' => $this->started_at?->toISOString(),
            'ended_at' => $this->ended_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
