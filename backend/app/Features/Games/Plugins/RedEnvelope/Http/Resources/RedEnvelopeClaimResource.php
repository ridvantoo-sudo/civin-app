<?php

namespace App\Features\Games\Plugins\RedEnvelope\Http\Resources;

use App\Features\Games\Models\RedEnvelopeClaim;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RedEnvelopeClaim */
final class RedEnvelopeClaimResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'envelope_id' => $this->envelope_id,
            'user_id' => $this->user_id,
            'play_id' => $this->play_id,
            'reward_type' => $this->rewardType(),
            'reward_amount' => $this->rewardAmount(),
            'idempotency_key' => $this->idempotency_key,
            'metadata' => $this->metadata,
            'claimed_at' => $this->claimed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
