<?php

namespace App\Features\Couple\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CoupleTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $task */
        $task = $this->resource;

        return [
            'id' => $task['id'],
            'key' => $task['key'],
            'title' => $task['title'],
            'description' => $task['description'],
            'period' => $task['period'],
            'target_value' => $task['target_value'],
            'reward_xp' => $task['reward_xp'],
            'progress' => $task['progress'],
            'completed_at' => $task['completed_at'],
            'claimed_at' => $task['claimed_at'],
            'is_claimable' => $task['is_claimable'],
        ];
    }
}
