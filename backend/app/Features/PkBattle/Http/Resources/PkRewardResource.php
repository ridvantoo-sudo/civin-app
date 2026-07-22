<?php

namespace App\Features\PkBattle\Http\Resources;

use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PkRewardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pk_battle_id' => $this->pk_battle_id,
            'winner_id' => $this->winner_id,
            'reward_type' => $this->reward_type,
            'amount' => $this->amount,
            'winner' => new SocialUserResource($this->whenLoaded('winner')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
