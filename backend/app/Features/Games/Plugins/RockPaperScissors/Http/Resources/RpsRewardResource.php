<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Http\Resources;

use App\Features\Games\Plugins\RockPaperScissors\Models\RpsReward;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RpsReward */
final class RpsRewardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'match_id' => $this->match_id,
            'tournament_id' => $this->tournament_id,
            'user_id' => $this->user_id,
            'reward_type' => $this->reward_type,
            'amount' => (int) $this->amount,
            'currency' => $this->currency,
            'rank' => $this->rank,
            'is_vip' => (bool) $this->is_vip,
            'is_tournament' => (bool) $this->is_tournament,
            'is_refund' => (bool) $this->is_refund,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
