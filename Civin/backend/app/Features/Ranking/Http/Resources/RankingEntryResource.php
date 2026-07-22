<?php

namespace App\Features\Ranking\Http\Resources;

use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Features\Ranking\Models\Ranking */
final class RankingEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'rank' => $this->rank,
            'score' => $this->score,
            'user' => new SocialUserResource($this->whenLoaded('user')),
        ];
    }
}
