<?php

namespace App\Features\CoinSeller\Http\Resources;

use App\Features\CoinSeller\Models\CoinRechargeCode;
use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CoinRechargeCode */
final class CoinRechargeCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $redeemer = $this->relationLoaded('redeemer') ? $this->redeemer : null;

        return [
            'id' => $this->id,
            'coin_seller_id' => $this->coin_seller_id,
            'code' => $this->code,
            'coins' => (int) $this->coins,
            'status' => $this->status,
            'expires_at' => $this->expires_at?->toISOString(),
            'redeemed_at' => $this->redeemed_at?->toISOString(),
            'revoked_at' => $this->revoked_at?->toISOString(),
            'redeemer' => $redeemer === null ? null : new SocialUserResource($redeemer),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
