<?php

namespace App\Features\Reports\Http\Resources;

use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'details' => $this->details,
            'status' => $this->status,
            'review_notes' => $this->review_notes,
            'reported_user' => new SocialUserResource($this->whenLoaded('reportedUser')),
            'reporter' => $this->when(
                (bool) $request->user()?->is_admin,
                fn () => new SocialUserResource($this->whenLoaded('reporter')),
            ),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
