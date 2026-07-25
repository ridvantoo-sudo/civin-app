<?php

namespace App\Features\SocialEngagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Features\SocialEngagement\Models\MentorshipRequest */
final class MentorshipRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'requester_id' => $this->requester_id,
            'target_id' => $this->target_id,
            'role' => $this->role,
            'message' => $this->message,
            'status' => $this->status,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'requester' => $this->whenLoaded('requester', fn () => [
                'id' => $this->requester->id,
                'display_name' => $this->requester->profile?->display_name ?? $this->requester->name,
            ]),
        ];
    }
}
