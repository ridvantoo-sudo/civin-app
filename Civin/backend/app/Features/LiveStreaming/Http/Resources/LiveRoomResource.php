<?php

namespace App\Features\LiveStreaming\Http\Resources;

use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class LiveRoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail,
            'status' => $this->status,
            'viewer_count' => $this->viewer_count,
            'host' => new SocialUserResource($this->whenLoaded('host')),
            'category' => new LiveCategoryResource($this->whenLoaded('category')),
            'started_at' => $this->started_at?->toISOString(),
            'ended_at' => $this->ended_at?->toISOString(),
        ];
    }
}
