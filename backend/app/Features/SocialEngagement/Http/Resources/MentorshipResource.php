<?php

namespace App\Features\SocialEngagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Features\SocialEngagement\Models\Mentorship */
final class MentorshipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mentor_id' => $this->mentor_id,
            'mentee_id' => $this->mentee_id,
            'status' => $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'mentor' => $this->whenLoaded('mentor', fn () => [
                'id' => $this->mentor->id,
                'display_name' => $this->mentor->profile?->display_name ?? $this->mentor->name,
            ]),
            'mentee' => $this->whenLoaded('mentee', fn () => [
                'id' => $this->mentee->id,
                'display_name' => $this->mentee->profile?->display_name ?? $this->mentee->name,
            ]),
        ];
    }
}
