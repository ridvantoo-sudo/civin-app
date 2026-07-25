<?php

namespace App\Features\Profiles\Http\Resources;

use App\Features\Blocking\Models\Block;
use App\Features\Followers\Models\Follow;
use App\Features\Levels\Models\UserLevelProgress;
use App\Features\Levels\Support\XpCurve;
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

        $progress = UserLevelProgress::query()
            ->where('user_id', $this->user_id)
            ->first();
        $level = (int) ($progress?->level ?? $this->level ?? 1);
        $xpIntoLevel = (int) ($progress?->xp_into_level ?? 0);
        $xpTotal = (int) ($progress?->xp_total ?? 0);
        $xpToNext = XpCurve::xpRequiredForLevel($level);
        $progressPercent = $xpToNext === 0
            ? 100.0
            : round(($xpIntoLevel / $xpToNext) * 100, 2);

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
            'country_region' => $this->whenLoaded('countryRegion'),
            'level' => $level,
            'xp_total' => $xpTotal,
            'xp_into_level' => $xpIntoLevel,
            'xp_to_next' => $xpToNext,
            'level_progress_percent' => min(100.0, $progressPercent),
            'is_vip' => $this->is_vip,
            'is_svip' => (bool) $this->is_svip,
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
