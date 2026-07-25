<?php

namespace App\Features\SocialEngagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Features\SocialEngagement\Models\MentorshipSession */
final class MentorshipSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mentorship_id' => $this->mentorship_id,
            'logged_by' => $this->logged_by,
            'duration_minutes' => $this->duration_minutes,
            'notes' => $this->notes,
            'mentor_coins' => $this->mentor_coins,
            'mentee_coins' => $this->mentee_coins,
            'held_at' => $this->held_at?->toIso8601String(),
        ];
    }
}
