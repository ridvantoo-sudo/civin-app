<?php

namespace App\Features\Games\Plugins\TreasureBox\Listeners;

use App\Features\Games\Models\GameHistory;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Models\TreasureBoxHistory;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureCreated;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureExpired;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureFinished;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureJoined;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureOpened;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureRewarded;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureScheduled;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureWinnerSelected;

final class RecordTreasureBoxHistory
{
    public function handle(
        TreasureCreated|TreasureScheduled|TreasureJoined|TreasureOpened|TreasureWinnerSelected|TreasureRewarded|TreasureExpired|TreasureFinished $event
    ): void {
        $treasure = $event->treasure;

        $eventType = match (true) {
            $event instanceof TreasureScheduled => 'scheduled',
            $event instanceof TreasureJoined => 'joined',
            $event instanceof TreasureOpened => 'opened',
            $event instanceof TreasureWinnerSelected => 'winner_selected',
            $event instanceof TreasureRewarded => 'rewarded',
            $event instanceof TreasureExpired => 'expired',
            $event instanceof TreasureFinished => 'finished',
            default => 'created',
        };

        $userId = match (true) {
            $event instanceof TreasureJoined => $event->participant->user_id,
            $event instanceof TreasureWinnerSelected, $event instanceof TreasureRewarded => $event->winner->user_id,
            default => $treasure->ownerId(),
        };

        $payload = [
            'treasure_box_id' => $treasure->id,
            'status' => $treasure->status,
            'reward_type' => $treasure->rewardType(),
            'total_amount' => (int) $treasure->total_amount,
            'remaining_amount' => $treasure->amountLeft(),
            'participant_count' => (int) $treasure->participant_count,
            'winner_count' => (int) $treasure->winner_count,
        ];

        if ($event instanceof TreasureJoined) {
            $payload['participant_id'] = $event->participant->id;
        }

        if ($event instanceof TreasureWinnerSelected || $event instanceof TreasureRewarded) {
            $payload['winner_id'] = $event->winner->id;
            $payload['reward_amount'] = $event->winner->rewardAmount();
            $payload['is_jackpot'] = (bool) $event->winner->is_jackpot;
        }

        if ($event instanceof TreasureExpired) {
            $payload['refunded_amount'] = $event->refundedAmount;
        }

        TreasureBoxHistory::query()->create([
            'treasure_box_id' => $treasure->getKey(),
            'user_id' => $userId,
            'event_type' => $eventType,
            'payload' => $payload,
            'created_at' => now(),
        ]);

        if ($treasure->game_id === null) {
            return;
        }

        if (! ($event instanceof TreasureCreated || $event instanceof TreasureRewarded)) {
            return;
        }

        $isWin = $event instanceof TreasureRewarded;
        $playId = $isWin ? $event->winner->play_id : $treasure->game_play_id;
        $historyUserId = $isWin ? $event->winner->user_id : $treasure->ownerId();

        if ($playId === null || $historyUserId === null) {
            return;
        }

        if (GameHistory::query()->where('play_id', $playId)->exists()) {
            return;
        }

        $paysCoins = $treasure->paysCoins();
        $totalAmount = (int) $treasure->total_amount;
        $rewardAmount = $isWin ? $event->winner->rewardAmount() : 0;

        GameHistory::query()->create([
            'user_id' => $historyUserId,
            'game_id' => $treasure->game_id,
            'play_id' => $playId,
            'result' => $isWin ? GamePlay::RESULT_CLAIMED : GamePlay::RESULT_LOST,
            'bet_coins' => $isWin || ! $paysCoins ? 0 : $totalAmount,
            'reward_coins' => $isWin && $paysCoins ? $rewardAmount : 0,
            'snapshot' => [
                'treasure_box_id' => $treasure->id,
                'winner_id' => $isWin ? $event->winner->id : null,
                'action' => $isWin ? 'win' : 'create',
                'reward_type' => $treasure->rewardType(),
                'reward_amount' => $rewardAmount,
                'type' => $treasure->type,
                'visibility' => $treasure->visibility,
                'eligibility_scope' => $treasure->eligibility_scope,
                'total_amount' => $totalAmount,
                'winner_count' => (int) $treasure->winner_count,
                'participant_count' => (int) $treasure->participant_count,
            ],
            'created_at' => now(),
        ]);
    }
}
