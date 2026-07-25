<?php

namespace App\Features\Games\Plugins\RedEnvelope\Support;

use App\Features\Games\Models\RedEnvelope;
use App\Features\Games\Models\RedEnvelopeClaim;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * Shared channel + payload shaping for red envelope broadcasts.
 *
 * Payloads intentionally expose only settled state (remaining amount / slots)
 * so a client can never infer the shares that future claimers will receive.
 */
final class RedEnvelopeBroadcast
{
    /** @return list<PrivateChannel> */
    public static function channelsFor(RedEnvelope $envelope, ?string $extraUserId = null): array
    {
        $channels = [];

        foreach (array_unique(array_filter([$envelope->ownerId(), $extraUserId])) as $userId) {
            $channels[] = new PrivateChannel('user.games.'.$userId);
            $channels[] = new PrivateChannel('user.red-envelope.'.$userId);
        }

        if ($envelope->room_id !== null) {
            $channels[] = new PrivateChannel(sprintf(
                'room.red-envelope.%s.%s',
                $envelope->room_type ?? 'unknown',
                $envelope->room_id,
            ));
        }

        return $channels;
    }

    /** @return array<string, mixed> */
    public static function envelopePayload(RedEnvelope $envelope): array
    {
        return [
            'event_id' => $envelope->id,
            'envelope_id' => $envelope->id,
            'creator_id' => $envelope->ownerId(),
            'game_id' => $envelope->game_id,
            'room_id' => $envelope->room_id,
            'room_type' => $envelope->room_type,
            'reward_type' => $envelope->rewardType(),
            'distribution_type' => $envelope->distribution_type,
            'visibility' => $envelope->visibility,
            'claim_scope' => $envelope->claim_scope,
            'total_amount' => (int) ($envelope->total_amount ?? $envelope->total_coins ?? 0),
            'remaining_amount' => $envelope->amountLeft(),
            'winner_count' => (int) ($envelope->winner_count ?? $envelope->total_slots ?? 0),
            'remaining_count' => $envelope->slotsLeft(),
            'message' => $envelope->message,
            'status' => $envelope->status,
            'expires_at' => $envelope->expires_at?->toISOString(),
            'created_at' => $envelope->created_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    public static function claimPayload(RedEnvelope $envelope, RedEnvelopeClaim $claim): array
    {
        return array_merge(self::envelopePayload($envelope), [
            'event_id' => $claim->id,
            'claim_id' => $claim->id,
            'claimer_id' => $claim->user_id,
            'reward_amount' => $claim->rewardAmount(),
            'claim_reward_type' => $claim->rewardType(),
            'claimed_at' => $claim->claimed_at?->toISOString(),
        ]);
    }
}
