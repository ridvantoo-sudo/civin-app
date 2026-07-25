<?php

namespace App\Features\FamilyClub\Http\Resources;

use App\Features\FamilyClub\Models\FamilyMessage;
use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FamilyMessage */
final class FamilyMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->relationLoaded('user') ? $this->user : null;

        return [
            'id' => $this->id,
            'family_id' => $this->family_id,
            'body' => $this->body,
            'user' => $user === null ? null : new SocialUserResource($user),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
