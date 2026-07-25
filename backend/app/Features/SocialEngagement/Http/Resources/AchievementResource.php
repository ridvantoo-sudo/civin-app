<?php

namespace App\Features\SocialEngagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Features\SocialEngagement\Models\Achievement */
final class AchievementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'category' => $this->category,
            'metric' => $this->metric,
            'target' => $this->target,
            'coins_reward' => $this->coins_reward,
            'diamonds_reward' => $this->diamonds_reward,
            'xp_reward' => $this->xp_reward,
            'user_progress' => $this->user_progress ?? 0,
            'is_unlocked' => (bool) ($this->is_unlocked ?? false),
            'is_claimed' => (bool) ($this->is_claimed ?? false),
            'unlocked_at' => $this->unlocked_at?->toIso8601String(),
            'claimed_at' => $this->claimed_at?->toIso8601String(),
        ];
    }
}
