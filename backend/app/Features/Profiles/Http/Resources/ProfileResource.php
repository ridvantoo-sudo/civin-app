<?php

namespace App\Features\Profiles\Http\Resources;

use App\Features\Blocking\Models\Block;
use App\Features\Followers\Models\Follow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $isAnotherUser = $viewer && (string) $viewer->id !== (string) $this->user_id;
        $followStatus = $isAnotherUser
            ? Follow::query()
                ->where('follower_id', $viewer->id)
                ->where('followed_id', $this->user_id)
                ->value('status')
            : null;
        $isBlocked = $isAnotherUser && Block::query()
            ->where('blocker_id', $viewer->id)
            ->where('blocked_id', $this->user_id)
            ->exists();

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'username' => $this->whenLoaded('user', fn () => $this->user->username),
            'nickname' => $this->display_name,
            'display_name' => $this->display_name,
            'bio' => $this->bio,
            'avatar_url' => $this->avatar_url,
            'cover_image_url' => $this->cover_image_url,
            'birthday' => $this->birth_date?->toDateString(),
            'birth_date' => $this->birth_date?->toDateString(),
            'gender' => $this->gender,
            'country' => $this->whenLoaded('country'),
            'level' => $this->level,
            'is_vip' => $this->is_vip,
            'is_private' => $this->is_private,
            'followers_count' => $this->followers_count,
            'following_count' => $this->following_count,
            'likes_count' => $this->likes_count,
            'is_online' => $this->whenLoaded('user', fn () => (bool) $this->user->socialStatus?->is_online),
            'is_live' => $this->whenLoaded('user', fn () => (bool) $this->user->socialStatus?->is_live),
            'follow_status' => $followStatus,
            'is_blocked' => $isBlocked,
        ];
    }
}
