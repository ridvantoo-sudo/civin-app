<?php

namespace App\Features\CreatorTools\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Features\CreatorTools\Models\RtmpIngestCredential */
final class RtmpIngestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'ingest_url' => $this->ingest_url,
            'stream_key' => $this->stream_key,
            'status' => $this->status,
            'expires_at' => $this->expires_at?->toISOString(),
            'obs_server' => $this->ingest_url,
            'obs_stream_key' => $this->stream_key,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
