<?php

namespace App\Features\Games\Plugins\Quiz\Http\Resources;

use App\Features\Games\Plugins\Quiz\Models\QuizTournament;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QuizTournament */
final class QuizTournamentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'rounds' => (int) $this->rounds,
            'current_round' => (int) $this->current_round,
            'max_players' => (int) $this->max_players,
            'entry_fee_coins' => (int) $this->entry_fee_coins,
            'reward_type' => $this->reward_type,
            'reward_pool' => (int) $this->reward_pool,
            'prize_structure' => $this->prize_structure,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
        ];
    }
}
