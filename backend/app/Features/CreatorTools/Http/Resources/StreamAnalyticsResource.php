<?php

namespace App\Features\CreatorTools\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Features\CreatorTools\Models\StreamAnalytics */
final class StreamAnalyticsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'host_id' => $this->host_id,
            'duration_seconds' => $this->duration_seconds,
            'peak_viewers' => $this->peak_viewers,
            'unique_viewers' => $this->unique_viewers,
            'chat_messages' => $this->chat_messages,
            'gifts_count' => $this->gifts_count,
            'gifts_coins' => $this->gifts_coins,
            'new_followers' => $this->new_followers,
            'avg_watch_seconds' => $this->avg_watch_seconds,
            'timeline' => $this->timeline,
            'finalized_at' => $this->finalized_at?->toISOString(),
        ];
    }
}
