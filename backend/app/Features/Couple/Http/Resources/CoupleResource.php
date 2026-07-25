<?php

namespace App\Features\Couple\Http\Resources;

use App\Features\Couple\Models\Couple;
use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Couple */
final class CoupleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userOne = $this->relationLoaded('userOne') ? $this->userOne : null;
        $userTwo = $this->relationLoaded('userTwo') ? $this->userTwo : null;
        $viewer = $request->user();
        $partner = null;

        if ($viewer !== null) {
            if ($this->user_one_id === $viewer->getKey()) {
                $partner = $userTwo;
            } elseif ($this->user_two_id === $viewer->getKey()) {
                $partner = $userOne;
            }
        }

        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'cover_url' => $this->cover_url,
            'badge_key' => $this->badge_key,
            'badge_url' => $this->badge_url,
            'level' => $this->level,
            'xp' => $this->xp,
            'xp_to_next_level' => $this->xp_to_next_level,
            'status' => $this->status,
            'anniversary_at' => $this->anniversary_at?->toISOString(),
            'ranking_score' => $this->ranking_score,
            'total_gifts_sent' => $this->total_gifts_sent,
            'user_one' => $userOne === null ? null : new SocialUserResource($userOne),
            'user_two' => $userTwo === null ? null : new SocialUserResource($userTwo),
            'partner' => $partner === null ? null : new SocialUserResource($partner),
            'ended_at' => $this->ended_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
