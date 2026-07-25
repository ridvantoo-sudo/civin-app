<?php

namespace App\Features\Games\Plugins\Quiz\Http\Resources;

use App\Features\Games\Plugins\Quiz\Models\QuizPlayer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QuizPlayer */
final class QuizPlayerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'team_id' => $this->team_id,
            'team_name' => $this->team_name,
            'is_host' => (bool) $this->is_host,
            'score' => (int) $this->score,
            'correct_count' => (int) $this->correct_count,
            'wrong_count' => (int) $this->wrong_count,
            'streak' => (int) $this->streak,
            'best_streak' => (int) $this->best_streak,
            'answers_count' => (int) $this->answers_count,
            'rank' => $this->rank,
            'is_winner' => (bool) $this->is_winner,
            'joined_at' => $this->joined_at?->toISOString(),
        ];
    }
}
