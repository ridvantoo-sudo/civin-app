<?php

namespace App\Features\Games\Plugins\RedEnvelope\Listeners;

use App\Features\Games\Models\GameHistory;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Plugins\RedEnvelope\Events\EnvelopeClaimed;
use App\Features\Games\Plugins\RedEnvelope\Events\EnvelopeCreated;

final class RecordRedEnvelopeHistory
{
    public function handle(EnvelopeCreated|EnvelopeClaimed $event): void
    {
        $envelope = $event->envelope;

        if ($envelope->game_id === null) {
            return;
        }

        $isClaim = $event instanceof EnvelopeClaimed;
        $playId = $isClaim ? $event->claim->play_id : $envelope->game_play_id;
        $userId = $isClaim ? $event->claim->user_id : $envelope->ownerId();

        if ($playId === null || $userId === null) {
            return;
        }

        if (GameHistory::query()->where('play_id', $playId)->exists()) {
            return;
        }

        $paysCoins = $envelope->paysCoins();
        $totalAmount = (int) ($envelope->total_amount ?? $envelope->total_coins ?? 0);
        $rewardAmount = $isClaim ? $event->claim->rewardAmount() : 0;

        GameHistory::query()->create([
            'user_id' => $userId,
            'game_id' => $envelope->game_id,
            'play_id' => $playId,
            'result' => $isClaim ? GamePlay::RESULT_CLAIMED : GamePlay::RESULT_LOST,
            'bet_coins' => $isClaim || ! $paysCoins ? 0 : $totalAmount,
            'reward_coins' => $isClaim && $paysCoins ? $rewardAmount : 0,
            'snapshot' => [
                'envelope_id' => $envelope->id,
                'claim_id' => $isClaim ? $event->claim->id : null,
                'action' => $isClaim ? 'claim' : 'create',
                'reward_type' => $envelope->rewardType(),
                'reward_amount' => $rewardAmount,
                'distribution_type' => $envelope->distribution_type,
                'visibility' => $envelope->visibility,
                'claim_scope' => $envelope->claim_scope,
                'total_amount' => $totalAmount,
                'winner_count' => (int) ($envelope->winner_count ?? $envelope->total_slots ?? 0),
                'remaining_amount' => $envelope->amountLeft(),
                'remaining_count' => $envelope->slotsLeft(),
            ],
            'created_at' => now(),
        ]);
    }
}
