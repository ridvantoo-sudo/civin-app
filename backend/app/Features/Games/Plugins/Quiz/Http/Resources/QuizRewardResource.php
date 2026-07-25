<?php

namespace App\Features\Games\Plugins\Quiz\Http\Resources;

use App\Features\Games\Plugins\Quiz\Models\QuizReward;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QuizReward */
final class QuizRewardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'user_id' => $this->user_id,
            'reward_type' => $this->reward_type,
            'amount' => (int) $this->amount,
            'currency' => $this->currency,
            'rank' => $this->rank,
            'status' => $this->status,
            'is_jackpot' => (bool) $this->is_jackpot,
            'is_mystery' => (bool) $this->is_mystery,
            'is_vip' => (bool) $this->is_vip,
        ];
    }
}
