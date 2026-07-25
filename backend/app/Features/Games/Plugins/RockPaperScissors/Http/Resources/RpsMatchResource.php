<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Http\Resources;

use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RpsMatch */
final class RpsMatchResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isChallenger = $user !== null && (string) $this->challenger_id === (string) $user->getKey();
        $isPlayer = $user !== null && $this->roleFor((string) $user->getKey()) !== null;

        return [
            'id' => $this->id,
            'config_id' => $this->config_id,
            'game_id' => $this->game_id,
            'tournament_id' => $this->tournament_id,
            'bracket_id' => $this->bracket_id,
            'parent_match_id' => $this->parent_match_id,
            'challenger_id' => $this->challenger_id,
            'opponent_id' => $this->opponent_id,
            'winner_id' => $this->winner_id,
            'forfeited_by' => $this->forfeited_by,
            'mode' => $this->mode,
            'matchmaking' => $this->matchmaking,
            'status' => $this->status,
            'result' => $this->result,
            'visibility' => $this->visibility,
            'eligibility' => $this->eligibility,
            'reward_type' => $this->reward_type,
            'entry_fee_coins' => (int) $this->entry_fee_coins,
            'pot_coins' => (int) $this->pot_coins,
            'rake_coins' => (int) $this->rake_coins,
            'rounds_target' => (int) $this->rounds_target,
            'wins_required' => (int) $this->wins_required,
            'max_rounds' => (int) $this->max_rounds,
            'rounds_played' => (int) $this->rounds_played,
            'challenger_score' => (int) $this->challenger_score,
            'opponent_score' => (int) $this->opponent_score,
            'draw_count' => (int) $this->draw_count,
            'current_round' => (int) $this->current_round,
            'current_round_id' => $this->current_round_id,
            'round_timeout_seconds' => (int) $this->round_timeout_seconds,
            'room_id' => $this->room_id,
            'room_type' => $this->room_type,
            'is_vip_arena' => (bool) $this->is_vip_arena,
            'is_rematch' => (bool) $this->is_rematch,
            'rematch_count' => (int) $this->rematch_count,
            'your_role' => $user === null ? null : $this->roleFor((string) $user->getKey()),
            'moves_available' => $this->moveRules()['moves'] ?? [],
            'accept_deadline_at' => $this->accept_deadline_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'idempotency_key' => $this->when($isChallenger, $this->idempotency_key),
            'whitelist_user_ids' => $this->when($isChallenger, $this->whitelist_user_ids),
            'audit_log' => $this->when($isPlayer, $this->audit_log),
            'rounds' => RpsRoundResource::collection($this->whenLoaded('rounds')),
            'rewards' => RpsRewardResource::collection($this->whenLoaded('rewards')),
        ];
    }
}
