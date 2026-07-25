<?php

namespace App\Features\Games\Plugins\RedEnvelope\Http\Resources;

use App\Features\Games\Models\RedEnvelope;
use App\Features\Games\Models\RedEnvelopeClaim;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Only settled state is exposed. The per-slot allocation is drawn at claim
 * time, so there is nothing here a client could use to predict future shares.
 *
 * @mixin RedEnvelope
 */
final class RedEnvelopeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewerId = $request->user()?->getKey();
        $isOwner = $viewerId !== null && $this->ownerId() === $viewerId;

        return [
            'id' => $this->id,
            'game_id' => $this->game_id,
            'config_id' => $this->config_id,
            'creator_id' => $this->ownerId(),
            'room_id' => $this->room_id,
            'room_type' => $this->room_type,
            'reward_type' => $this->rewardType(),
            'distribution_type' => $this->distribution_type,
            'visibility' => $this->visibility,
            'claim_scope' => $this->claim_scope,
            'total_amount' => (int) ($this->total_amount ?? $this->total_coins ?? 0),
            'remaining_amount' => $this->amountLeft(),
            'winner_count' => (int) ($this->winner_count ?? $this->total_slots ?? 0),
            'remaining_count' => $this->slotsLeft(),
            'claims_count' => $this->when($this->claims_count !== null, fn (): int => (int) $this->claims_count),
            'message' => $this->message,
            'status' => $this->status,
            'is_open' => $this->isOpen(),
            'is_expired' => $this->isExpired(),
            'is_finished' => $this->isFinished(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'idempotency_key' => $this->when($isOwner, fn (): ?string => $this->idempotency_key),
            'whitelist_user_ids' => $this->when($isOwner, fn (): ?array => $this->whitelist_user_ids),
            'metadata' => $this->metadata,
            'my_claim' => $this->when(
                $this->relationLoaded('claims'),
                fn () => $this->resolveViewerClaim($viewerId),
            ),
        ];
    }

    /** @return array<string, mixed>|null */
    private function resolveViewerClaim(?string $viewerId): ?array
    {
        if ($viewerId === null) {
            return null;
        }

        $claim = $this->claims->first(
            fn (RedEnvelopeClaim $candidate): bool => $candidate->user_id === $viewerId,
        );

        if ($claim === null) {
            return null;
        }

        return (new RedEnvelopeClaimResource($claim))->resolve();
    }
}
