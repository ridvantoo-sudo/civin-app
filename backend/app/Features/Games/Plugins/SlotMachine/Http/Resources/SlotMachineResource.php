<?php

namespace App\Features\Games\Plugins\SlotMachine\Http\Resources;

use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineSpin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SlotMachineSpin */
final class SlotMachineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $outcome = $this->outcome ?? [];
        // Never expose internal RNG rolls to clients.
        unset($outcome['rolls']);

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'config_id' => $this->config_id,
            'session_id' => $this->session_id,
            'game_id' => $this->game_id,
            'play_id' => $this->play_id,
            'reward_id' => $this->reward_id,
            'idempotency_key' => $this->idempotency_key,
            'status' => $this->status,
            'game_mode' => $this->game_mode,
            'bet_amount' => $this->bet_amount,
            'spin_count' => $this->spin_count,
            'auto_spin' => $this->auto_spin,
            'cost_coins' => $this->cost_coins,
            'reward_coins' => $this->reward_coins,
            'reward_diamonds' => $this->reward_diamonds,
            'free_spins_awarded' => $this->free_spins_awarded,
            'is_win' => $this->is_win,
            'is_jackpot' => $this->is_jackpot,
            'is_bonus' => $this->is_bonus,
            'is_free_spin' => $this->is_free_spin,
            'jackpot_type' => $this->jackpot_type,
            'jackpot_coins' => $this->jackpot_coins,
            'config_version' => $this->config_version,
            'grid' => $this->grid,
            'outcome' => $outcome,
            'metadata' => $this->metadata,
            'settled_at' => $this->settled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
