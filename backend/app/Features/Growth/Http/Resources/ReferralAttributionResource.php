<?php

namespace App\Features\Growth\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Features\Growth\Models\ReferralAttribution */
final class ReferralAttributionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'referrer_id' => $this->referrer_id,
            'invitee_id' => $this->invitee_id,
            'status' => $this->status,
            'referrer_coins' => $this->referrer_coins,
            'invitee_coins' => $this->invitee_coins,
            'rewarded_at' => $this->rewarded_at?->toISOString(),
            'code' => $this->whenLoaded('code', fn () => $this->code?->code),
        ];
    }
}
