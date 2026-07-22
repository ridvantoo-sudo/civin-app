<?php

namespace App\Features\Followers\Http\Resources;

use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class FollowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->relationLoaded('follower') && ! $this->relationLoaded('followed')
            ? $this->follower
            : $this->followed;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'accepted_at' => $this->accepted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'user' => new SocialUserResource($user),
        ];
    }
}
