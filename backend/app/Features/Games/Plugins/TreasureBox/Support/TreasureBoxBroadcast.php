<?php

namespace App\Features\Games\Plugins\TreasureBox\Support;

use App\Features\Games\Models\TreasureBox;
use App\Features\Games\Models\TreasureBoxParticipant;
use App\Features\Games\Models\TreasureBoxWinner;
use Illuminate\Broadcasting\PrivateChannel;

final class TreasureBoxBroadcast
{
    /** @return list<PrivateChannel> */
    public static function channelsFor(TreasureBox $treasure, ?string $extraUserId = null): array
    {
        $channels = [];

        foreach (array_unique(array_filter([$treasure->ownerId(), $extraUserId])) as $userId) {
            $channels[] = new PrivateChannel('user.games.'.$userId);
            $channels[] = new PrivateChannel('user.treasure-box.'.$userId);
        }

        if ($treasure->room_id !== null) {
            $channels[] = new PrivateChannel(sprintf(
                'room.treasure-box.%s.%s',
                $treasure->room_type ?? 'unknown',
                $treasure->room_id,
            ));
        }

        return $channels;
    }

    /** @return array<string, mixed> */
    public static function treasurePayload(TreasureBox $treasure): array
    {
        return [
            'event_id' => $treasure->id,
            'treasure_box_id' => $treasure->id,
            'creator_id' => $treasure->ownerId(),
            'game_id' => $treasure->game_id,
            'type' => $treasure->type,
            'room_id' => $treasure->room_id,
            'room_type' => $treasure->room_type,
            'reward_type' => $treasure->rewardType(),
            'distribution_type' => $treasure->distributionType(),
            'visibility' => $treasure->visibility,
            'eligibility_scope' => $treasure->eligibility_scope,
            'total_amount' => (int) $treasure->total_amount,
            'remaining_amount' => $treasure->amountLeft(),
            'winner_count' => (int) $treasure->winner_count,
            'participant_count' => (int) $treasure->participant_count,
            'message' => $treasure->message,
            'status' => $treasure->status,
            'countdown_seconds' => (int) $treasure->countdown_seconds,
            'claim_duration_seconds' => (int) $treasure->claim_duration_seconds,
            'scheduled_at' => $treasure->scheduled_at?->toISOString(),
            'countdown_started_at' => $treasure->countdown_started_at?->toISOString(),
            'opens_at' => $treasure->opens_at?->toISOString(),
            'draw_at' => $treasure->draw_at?->toISOString(),
            'expires_at' => $treasure->expires_at?->toISOString(),
            'created_at' => $treasure->created_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    public static function joinedPayload(TreasureBox $treasure, TreasureBoxParticipant $participant): array
    {
        return array_merge(self::treasurePayload($treasure), [
            'event_id' => $participant->id,
            'participant_id' => $participant->id,
            'joiner_id' => $participant->user_id,
            'joined_at' => $participant->joined_at?->toISOString(),
        ]);
    }

    /** @return array<string, mixed> */
    public static function winnerPayload(TreasureBox $treasure, TreasureBoxWinner $winner): array
    {
        return array_merge(self::treasurePayload($treasure), [
            'event_id' => $winner->id,
            'winner_id' => $winner->id,
            'user_id' => $winner->user_id,
            'reward_amount' => $winner->rewardAmount(),
            'winner_reward_type' => $winner->rewardType(),
            'is_jackpot' => (bool) $winner->is_jackpot,
            'rewarded_at' => $winner->rewarded_at?->toISOString(),
        ]);
    }
}
