<?php

namespace App\Features\Games\Plugins\Quiz\Http\Resources;

use App\Features\Games\Plugins\Quiz\Models\QuizSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QuizSession */
final class QuizResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'idempotency_key' => $this->idempotency_key,
            'mode' => $this->mode,
            'difficulty' => $this->difficulty,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'eligibility' => $this->eligibility,
            'reward_type' => $this->reward_type,
            'reward_pool' => $this->reward_pool,
            'bet_coins' => $this->bet_coins,
            'question_count' => $this->question_count,
            'time_per_question' => $this->time_per_question,
            'winner_count' => $this->winner_count,
            'current_round' => $this->current_round,
            'current_question_index' => $this->current_question_index,
            'current_question_id' => $this->when(
                $this->status === 'question',
                $this->current_question_id,
            ),
            'room_id' => $this->room_id,
            'room_type' => $this->room_type,
            'language' => $this->language,
            'player_count' => $this->player_count,
            'category_id' => $this->category_id,
            'tournament_id' => $this->tournament_id,
            'host_id' => $this->host_id,
            'question_started_at' => $this->question_started_at?->toISOString(),
            'question_ends_at' => $this->question_ends_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'players' => $this->whenLoaded('players', fn () => $this->players->map(fn ($p) => [
                'id' => $p->id,
                'user_id' => $p->user_id,
                'is_host' => $p->is_host,
                'score' => $p->score,
                'rank' => $p->rank,
                'is_winner' => $p->is_winner,
                'team_id' => $p->team_id,
                'team_name' => $p->team_name,
                'status' => $p->status,
            ])),
            'scores' => $this->whenLoaded('scores', fn () => $this->scores->map(fn ($s) => [
                'user_id' => $s->user_id,
                'total_score' => $s->total_score,
                'rank' => $s->rank,
                'is_winner' => $s->is_winner,
                'breakdown' => $s->breakdown,
            ])),
            'rewards' => $this->whenLoaded('rewards', fn () => $this->rewards->map(fn ($r) => [
                'id' => $r->id,
                'user_id' => $r->user_id,
                'reward_type' => $r->reward_type,
                'amount' => $r->amount,
                'rank' => $r->rank,
                'status' => $r->status,
            ])),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
