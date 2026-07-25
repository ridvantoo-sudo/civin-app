<?php

namespace App\Features\Games\Http\Resources;

use App\Features\Games\Models\GamePlay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GamePlay */
final class GamePlayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'game_id' => $this->game_id,
            'game_slug' => $this->whenLoaded('game', fn () => $this->game?->slug),
            'user_id' => $this->user_id,
            'match_id' => $this->match_id,
            'bet_coins' => $this->bet_coins,
            'bet_diamonds' => $this->bet_diamonds,
            'reward_coins' => $this->reward_coins,
            'reward_diamonds' => $this->reward_diamonds,
            'result' => $this->result,
            'status' => $this->status,
            'input' => $this->input,
            'outcome' => $this->outcome,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
