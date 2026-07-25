<?php

namespace App\Features\CreatorTools\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Features\CreatorTools\Models\StreamReplay */
final class StreamReplayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'host_id' => $this->host_id,
            'title' => $this->title,
            'status' => $this->status,
            'duration_seconds' => $this->duration_seconds,
            'peak_viewers' => $this->peak_viewers,
            'media_url' => $this->media_url,
            'thumbnail_url' => $this->thumbnail_url,
            'views_count' => $this->views_count,
            'expires_at' => $this->expires_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
