<?php

namespace App\Features\CoinSeller\Http\Resources;

use App\Features\CoinSeller\Models\CoinSellerInventoryLog;
use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CoinSellerInventoryLog */
final class CoinSellerInventoryLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $actor = $this->relationLoaded('actor') ? $this->actor : null;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => (int) $this->amount,
            'balance_after' => (int) $this->balance_after,
            'notes' => $this->notes,
            'actor' => $actor === null ? null : new SocialUserResource($actor),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
