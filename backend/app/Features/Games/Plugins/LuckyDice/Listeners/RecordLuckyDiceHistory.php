<?php

namespace App\Features\Games\Plugins\LuckyDice\Listeners;

use App\Features\Games\Models\GameHistory;
use App\Features\Games\Plugins\LuckyDice\Events\LuckyDicePlayed;

final class RecordLuckyDiceHistory
{
    public function handle(LuckyDicePlayed $event): void
    {
        $bet = $event->bet;

        if ($bet->game_id === null) {
            return;
        }

        $exists = GameHistory::query()
            ->where('play_id', $bet->play_id)
            ->exists();

        if ($exists) {
            return;
        }

        $result = $bet->is_win ? 'won' : 'lost';

        GameHistory::query()->create([
            'user_id' => $bet->user_id,
            'game_id' => $bet->game_id,
            'play_id' => $bet->play_id,
            'result' => $result,
            'bet_coins' => $bet->bet_amount,
            'reward_coins' => $bet->reward_currency === 'coins' ? $bet->reward_amount : 0,
            'snapshot' => [
                'bet_id' => $bet->id,
                'bet_mode' => $bet->bet_mode,
                'prediction' => $bet->prediction,
                'reward_type' => $bet->reward_type,
                'reward_amount' => $bet->reward_amount,
                'is_jackpot' => $bet->is_jackpot,
                'is_win' => $bet->is_win,
                'multiplier' => $bet->multiplier,
                'outcome' => $bet->outcome,
            ],
            'created_at' => now(),
        ]);
    }
}
