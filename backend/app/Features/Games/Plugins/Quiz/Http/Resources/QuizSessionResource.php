<?php

namespace App\Features\Games\Plugins\Quiz\Http\Resources;

use App\Features\Games\Plugins\Quiz\Models\QuizSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QuizSession */
final class QuizSessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isHost = $user !== null && (string) $this->host_id === (string) $user->getKey();

        return [
            'id' => $this->id,
            'config_id' => $this->config_id,
            'game_id' => $this->game_id,
            'host_id' => $this->host_id,
            'tournament_id' => $this->tournament_id,
            'category_id' => $this->category_id,
            'mode' => $this->mode,
            'difficulty' => $this->difficulty,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'eligibility' => $this->eligibility,
            'reward_type' => $this->reward_type,
            'reward_pool' => (int) $this->reward_pool,
            'bet_coins' => (int) $this->bet_coins,
            'question_count' => (int) $this->question_count,
            'time_per_question' => (int) $this->time_per_question,
            'winner_count' => (int) $this->winner_count,
            'current_round' => (int) $this->current_round,
            'current_question_index' => (int) $this->current_question_index,
            'current_question_id' => $this->current_question_id,
            'player_count' => (int) $this->player_count,
            'room_id' => $this->room_id,
            'room_type' => $this->room_type,
            'language' => $this->language,
            'question_started_at' => $this->question_started_at?->toISOString(),
            'question_ends_at' => $this->question_ends_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'idempotency_key' => $this->when($isHost, $this->idempotency_key),
            'whitelist_user_ids' => $this->when($isHost, $this->whitelist_user_ids),
            'team_settings' => $this->team_settings,
            'players' => QuizPlayerResource::collection($this->whenLoaded('players')),
            'scores' => $this->whenLoaded('scores'),
            'rewards' => QuizRewardResource::collection($this->whenLoaded('rewards')),
        ];
    }
}
