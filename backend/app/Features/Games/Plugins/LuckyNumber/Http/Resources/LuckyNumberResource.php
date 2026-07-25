<?php

namespace App\Features\Games\Plugins\LuckyNumber\Http\Resources;

use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LuckyNumberTicket */
final class LuckyNumberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $outcome = $this->outcome ?? [];
        // Never expose internal RNG rolls or server seeds to clients.
        unset($outcome['rolls'], $outcome['server_seed']);

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'config_id' => $this->config_id,
            'draw_id' => $this->draw_id,
            'game_id' => $this->game_id,
            'play_id' => $this->play_id,
            'reward_id' => $this->reward_id,
            'idempotency_key' => $this->idempotency_key,
            'status' => $this->status,
            'game_mode' => $this->game_mode,
            'min_number' => $this->min_number,
            'max_number' => $this->max_number,
            'picked_numbers' => $this->picked_numbers,
            'auto_pick' => $this->auto_pick,
            'repeat_ticket' => $this->repeat_ticket,
            'bet_amount' => $this->bet_amount,
            'cost_coins' => $this->cost_coins,
            'reward_coins' => $this->reward_coins,
            'reward_diamonds' => $this->reward_diamonds,
            'is_win' => $this->is_win,
            'is_jackpot' => $this->is_jackpot,
            'is_bonus' => $this->is_bonus,
            'jackpot_type' => $this->jackpot_type,
            'jackpot_coins' => $this->jackpot_coins,
            'config_version' => $this->config_version,
            'outcome' => $outcome,
            'metadata' => $this->metadata,
            'settled_at' => $this->settled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
