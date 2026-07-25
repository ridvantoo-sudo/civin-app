<?php

namespace App\Features\FamilyClub\Http\Resources;

use App\Features\FamilyClub\Models\FamilyInvite;
use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FamilyInvite */
final class FamilyInviteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $inviter = $this->relationLoaded('inviter') ? $this->inviter : null;
        $invitedUser = $this->relationLoaded('invitedUser') ? $this->invitedUser : null;

        return [
            'id' => $this->id,
            'family_id' => $this->family_id,
            'code' => $this->code,
            'role' => $this->role,
            'status' => $this->status,
            'expires_at' => $this->expires_at?->toISOString(),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'invited_by' => $inviter === null ? null : new SocialUserResource($inviter),
            'invited_user' => $invitedUser === null ? null : new SocialUserResource($invitedUser),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
