<?php

namespace App\Features\Games\Plugins\TreasureBox\Http\Resources;

use App\Features\Games\Models\TreasureBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TreasureBox */
final class TreasureBoxResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isOwner = $user !== null && $this->ownerId() === $user->getKey();

        $myParticipant = null;
        if ($user !== null && $this->relationLoaded('participants')) {
            $myParticipant = $this->participants->firstWhere('user_id', $user->getKey());
        }

        $myWinner = null;
        if ($user !== null && $this->relationLoaded('winners')) {
            $myWinner = $this->winners->firstWhere('user_id', $user->getKey());
        }

        return [
            'id' => $this->id,
            'game_id' => $this->game_id,
            'config_id' => $this->config_id,
            'creator_id' => $this->ownerId(),
            'type' => $this->type,
            'room_id' => $this->room_id,
            'room_type' => $this->room_type,
            'reward_type' => $this->rewardType(),
            'distribution_type' => $this->distributionType(),
            'visibility' => $this->visibility,
            'eligibility_scope' => $this->eligibility_scope,
            'total_amount' => (int) $this->total_amount,
            'remaining_amount' => $this->amountLeft(),
            'winner_count' => (int) $this->winner_count,
            'participant_count' => (int) $this->participant_count,
            'participants_count' => $this->participants_count ?? $this->participant_count,
            'winners_count' => $this->winners_count ?? null,
            'min_watch_time_seconds' => $this->min_watch_time_seconds,
            'min_user_level' => $this->min_user_level,
            'min_gift_value' => $this->min_gift_value,
            'countdown_seconds' => (int) $this->countdown_seconds,
            'claim_duration_seconds' => (int) $this->claim_duration_seconds,
            'auto_start' => (bool) $this->auto_start,
            'message' => $this->message,
            'status' => $this->status,
            'is_open' => $this->isOpen(),
            'is_expired' => $this->isExpired(),
            'is_finished' => $this->isFinished(),
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'countdown_started_at' => $this->countdown_started_at?->toISOString(),
            'opens_at' => $this->opens_at?->toISOString(),
            'draw_at' => $this->draw_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'idempotency_key' => $this->when($isOwner, $this->idempotency_key),
            'whitelist_user_ids' => $this->when($isOwner, $this->whitelist_user_ids),
            'my_participant' => $myParticipant === null ? null : (new TreasureBoxParticipantResource($myParticipant))->resolve($request),
            'my_win' => $myWinner === null ? null : (new TreasureBoxWinnerResource($myWinner))->resolve($request),
        ];
    }
}
