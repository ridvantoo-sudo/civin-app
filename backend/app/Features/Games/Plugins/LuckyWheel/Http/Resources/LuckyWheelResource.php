<?php

namespace App\Features\Games\Plugins\LuckyWheel\Http\Resources;

use App\Features\Games\Plugins\LuckyWheel\Models\LuckyWheelSpin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LuckyWheelSpin */
final class LuckyWheelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'config_id' => $this->config_id,
            'sector_id' => $this->sector_id,
            'game_id' => $this->game_id,
            'play_id' => $this->play_id,
            'reward_id' => $this->reward_id,
            'idempotency_key' => $this->idempotency_key,
            'status' => $this->status,
            'cost_coins' => $this->cost_coins,
            'reward_type' => $this->reward_type,
            'reward_amount' => $this->reward_amount,
            'reward_currency' => $this->reward_currency,
            'reward_reference' => $this->reward_reference,
            'is_jackpot' => $this->is_jackpot,
            'sector_position' => $this->sector_position,
            'sector_label' => $this->sector_label,
            'config_version' => $this->config_version,
            'outcome' => $this->outcome,
            'metadata' => $this->metadata,
            'settled_at' => $this->settled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
