<?php

namespace App\Features\Games\Plugins\TreasureBox\Listeners;

use App\Features\Games\Models\Game;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureCreated;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureExpired;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureFinished;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureJoined;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureOpened;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureRewarded;
use App\Features\Games\Repositories\Contracts\GameRepository;

final class RecordTreasureBoxAnalytics
{
    public function __construct(private readonly GameRepository $games) {}

    public function handle(
        TreasureCreated|TreasureJoined|TreasureOpened|TreasureRewarded|TreasureExpired|TreasureFinished $event
    ): void {
        $treasure = $event->treasure;
        $game = $treasure->game_id !== null
            ? Game::query()->find($treasure->game_id)
            : Game::query()->where('slug', Game::SLUG_TREASURE_BOX)->first();

        $actor = match (true) {
            $event instanceof TreasureJoined => $event->participant->user,
            $event instanceof TreasureRewarded => $event->winner->user,
            default => $treasure->creator,
        };

        $eventName = match (true) {
            $event instanceof TreasureJoined => 'treasure_joined',
            $event instanceof TreasureOpened => 'treasure_opened',
            $event instanceof TreasureRewarded => 'treasure_rewarded',
            $event instanceof TreasureExpired => 'treasure_expired',
            $event instanceof TreasureFinished => 'treasure_finished',
            default => 'treasure_created',
        };

        $payload = [
            'treasure_box_id' => $treasure->id,
            'creator_id' => $treasure->ownerId(),
            'type' => $treasure->type,
            'reward_type' => $treasure->rewardType(),
            'visibility' => $treasure->visibility,
            'eligibility_scope' => $treasure->eligibility_scope,
            'room_type' => $treasure->room_type,
            'room_id' => $treasure->room_id,
            'total_amount' => (int) $treasure->total_amount,
            'remaining_amount' => $treasure->amountLeft(),
            'winner_count' => (int) $treasure->winner_count,
            'participant_count' => (int) $treasure->participant_count,
            'status' => $treasure->status,
        ];

        if ($event instanceof TreasureJoined) {
            $payload['participant_id'] = $event->participant->id;
        }

        if ($event instanceof TreasureRewarded) {
            $payload['winner_id'] = $event->winner->id;
            $payload['reward_amount'] = $event->winner->rewardAmount();
            $payload['is_jackpot'] = (bool) $event->winner->is_jackpot;
        }

        if ($event instanceof TreasureExpired) {
            $payload['refunded_amount'] = $event->refundedAmount;
        }

        $this->games->trackAnalytics($actor, $game, $eventName, $payload);
    }
}
