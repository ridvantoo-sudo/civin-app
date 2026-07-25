<?php

namespace App\Features\FamilyClub\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class FamilyMissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $mission */
        $mission = $this->resource;

        return [
            'id' => $mission['id'],
            'key' => $mission['key'],
            'title' => $mission['title'],
            'description' => $mission['description'],
            'period' => $mission['period'],
            'target_value' => $mission['target_value'],
            'reward_xp' => $mission['reward_xp'],
            'reward_coins' => $mission['reward_coins'],
            'progress' => $mission['progress'],
            'completed_at' => $mission['completed_at'],
            'claimed_at' => $mission['claimed_at'],
            'is_claimable' => $mission['is_claimable'],
        ];
    }
}
