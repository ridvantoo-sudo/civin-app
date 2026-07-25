<?php

namespace App\Features\Games\Plugins\TreasureBox\Http\Resources;

use App\Features\Games\Models\TreasureBoxWinner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TreasureBoxWinner */
final class TreasureBoxWinnerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'treasure_box_id' => $this->treasure_box_id,
            'user_id' => $this->user_id,
            'participant_id' => $this->participant_id,
            'play_id' => $this->play_id,
            'reward_type' => $this->rewardType(),
            'reward_amount' => $this->rewardAmount(),
            'is_jackpot' => (bool) $this->is_jackpot,
            'idempotency_key' => $this->idempotency_key,
            'metadata' => $this->metadata,
            'rewarded_at' => $this->rewarded_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
