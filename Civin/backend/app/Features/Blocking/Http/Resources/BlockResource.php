<?php

namespace App\Features\Blocking\Http\Resources;

use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class BlockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => new SocialUserResource($this->blocked),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
