<?php

namespace App\Features\Challenges\Http\Resources;

use App\Features\Challenges\Models\UserChallengeProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UserChallengeProgress */
final class ChallengeHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $challenge = $this->challenge;

        return [
            'id' => $this->id,
            'challenge_id' => $this->challenge_id,
            'code' => $challenge?->code,
            'title' => $challenge?->title,
            'description' => $challenge?->description,
            'period' => $challenge?->period,
            'period_key' => $this->period_key,
            'task_type' => $challenge?->task_type,
            'target' => (int) ($challenge?->target ?? 0),
            'progress' => (int) $this->progress,
            'xp_awarded' => (int) ($this->xp_awarded ?? 0),
            'coins_awarded' => (int) ($this->coins_awarded ?? 0),
            'diamonds_awarded' => (int) ($this->diamonds_awarded ?? 0),
            'icon' => $challenge?->icon,
            'completed_at' => $this->completed_at?->toISOString(),
            'claimed_at' => $this->claimed_at?->toISOString(),
        ];
    }
}
