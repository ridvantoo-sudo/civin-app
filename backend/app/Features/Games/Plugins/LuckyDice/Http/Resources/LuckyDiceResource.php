<?php

namespace App\Features\Games\Plugins\LuckyDice\Http\Resources;

use App\Features\Games\Plugins\LuckyDice\Models\LuckyDiceBet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LuckyDiceBet */
final class LuckyDiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'config_id' => $this->config_id,
            'game_id' => $this->game_id,
            'play_id' => $this->play_id,
            'reward_id' => $this->reward_id,
            'result_id' => $this->result_id,
            'session_id' => $this->session_id,
            'idempotency_key' => $this->idempotency_key,
            'bet_mode' => $this->bet_mode,
            'prediction' => $this->prediction,
            'bet_amount' => $this->bet_amount,
            'status' => $this->status,
            'reward_type' => $this->reward_type,
            'reward_amount' => $this->reward_amount,
            'reward_currency' => $this->reward_currency,
            'reward_reference' => $this->reward_reference,
            'is_jackpot' => $this->is_jackpot,
            'is_win' => $this->is_win,
            'multiplier' => $this->multiplier,
            'config_version' => $this->config_version,
            'outcome' => $this->outcome,
            'metadata' => $this->metadata,
            'settled_at' => $this->settled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'result' => $this->when($this->relationLoaded('result') && $this->result !== null, fn (): array => [
                'id' => $this->result->id,
                'dice_1' => $this->result->dice_1,
                'dice_2' => $this->result->dice_2,
                'dice_3' => $this->result->dice_3,
                'total' => $this->result->total,
                'is_triple' => $this->result->is_triple,
                'result' => $this->result->result,
                'reward_type' => $this->result->reward_type,
                'reward_amount' => $this->result->reward_amount,
                'status' => $this->result->status,
            ]),
        ];
    }
}
