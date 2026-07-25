<?php

namespace App\Features\FamilyClub\Http\Resources;

use App\Features\FamilyClub\Models\Family;
use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Family */
final class FamilyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $owner = $this->relationLoaded('owner') ? $this->owner : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'logo' => $this->logo,
            'badge_key' => $this->badge_key,
            'badge_url' => $this->badge_url,
            'level' => $this->level,
            'xp' => $this->xp,
            'xp_to_next_level' => $this->xp_to_next_level,
            'status' => $this->status,
            'members_count' => $this->members_count,
            'max_members' => $this->max_members,
            'ranking_score' => $this->ranking_score,
            'total_gifts_received' => $this->total_gifts_received,
            'total_gifts_sent' => $this->total_gifts_sent,
            'wallet_coins' => $this->wallet_coins,
            'wallet_diamonds' => $this->wallet_diamonds,
            'owner' => $owner === null ? null : new SocialUserResource($owner),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
