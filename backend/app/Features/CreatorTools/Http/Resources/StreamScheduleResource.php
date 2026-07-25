<?php

namespace App\Features\CreatorTools\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Features\CreatorTools\Models\StreamSchedule */
final class StreamScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail,
            'category_id' => $this->category_id,
            'room_id' => $this->room_id,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'timezone' => $this->timezone,
            'started_at' => $this->started_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
