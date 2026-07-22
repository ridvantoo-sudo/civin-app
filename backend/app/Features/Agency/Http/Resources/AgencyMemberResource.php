<?php

namespace App\Features\Agency\Http\Resources;

use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Features\Agency\Models\AgencyMember */
final class AgencyMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->relationLoaded('user') ? $this->user : null;

        return [
            'id' => $this->id,
            'agency_id' => $this->agency_id,
            'role' => $this->role,
            'status' => $this->status,
            'message' => $this->message,
            'gross_earnings' => $this->gross_earnings,
            'commission_paid' => $this->commission_paid,
            'applied_at' => $this->applied_at?->toISOString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'removed_at' => $this->removed_at?->toISOString(),
            'user' => $user === null ? null : new SocialUserResource($user),
        ];
    }
}
