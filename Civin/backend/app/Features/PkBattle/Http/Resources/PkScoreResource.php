<?php

namespace App\Features\PkBattle\Http\Resources;

use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PkScoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pk_battle_id' => $this->pk_battle_id,
            'user_id' => $this->user_id,
            'score' => $this->score,
            'gift_coins' => $this->gift_coins,
            'user' => new SocialUserResource($this->whenLoaded('user')),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
