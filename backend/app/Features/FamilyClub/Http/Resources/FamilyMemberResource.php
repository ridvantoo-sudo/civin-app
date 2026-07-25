<?php

namespace App\Features\FamilyClub\Http\Resources;

use App\Features\FamilyClub\Models\FamilyMember;
use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FamilyMember */
final class FamilyMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->relationLoaded('user') ? $this->user : null;

        return [
            'id' => $this->id,
            'family_id' => $this->family_id,
            'role' => $this->role,
            'status' => $this->status,
            'message' => $this->message,
            'contribution_xp' => $this->contribution_xp,
            'contribution_gifts' => $this->contribution_gifts,
            'invited_at' => $this->invited_at?->toISOString(),
            'joined_at' => $this->joined_at?->toISOString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'removed_at' => $this->removed_at?->toISOString(),
            'user' => $user === null ? null : new SocialUserResource($user),
        ];
    }
}
