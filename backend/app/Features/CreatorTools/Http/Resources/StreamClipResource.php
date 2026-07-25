<?php

namespace App\Features\CreatorTools\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Features\CreatorTools\Models\StreamClip */
final class StreamClipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'host_id' => $this->host_id,
            'title' => $this->title,
            'kind' => $this->kind,
            'start_seconds' => $this->start_seconds,
            'end_seconds' => $this->end_seconds,
            'duration_seconds' => $this->duration_seconds,
            'status' => $this->status,
            'media_url' => $this->media_url,
            'thumbnail_url' => $this->thumbnail_url,
            'views_count' => $this->views_count,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
