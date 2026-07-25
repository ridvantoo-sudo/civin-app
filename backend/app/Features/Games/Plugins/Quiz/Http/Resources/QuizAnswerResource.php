<?php

namespace App\Features\Games\Plugins\Quiz\Http\Resources;

use App\Features\Games\Plugins\Quiz\Models\QuizAnswer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QuizAnswer */
final class QuizAnswerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'player_id' => $this->player_id,
            'user_id' => $this->user_id,
            'question_id' => $this->question_id,
            'selected_index' => $this->selected_index,
            'selected_text' => $this->selected_text,
            'is_correct' => (bool) $this->is_correct,
            'points_awarded' => (int) $this->points_awarded,
            'speed_bonus' => (int) $this->speed_bonus,
            'streak_bonus' => (int) $this->streak_bonus,
            'response_ms' => $this->response_ms,
            'timed_out' => (bool) $this->timed_out,
            'answered_at' => $this->answered_at?->toISOString(),
        ];
    }
}
