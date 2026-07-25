<?php

namespace App\Features\Games\Plugins\LuckyWheel\Listeners;

use App\Features\Games\Models\GameHistory;
use App\Features\Games\Plugins\LuckyWheel\Events\LuckyWheelPlayed;

final class RecordLuckyWheelHistory
{
    public function handle(LuckyWheelPlayed $event): void
    {
        $spin = $event->spin;

        if ($spin->game_id === null) {
            return;
        }

        $exists = GameHistory::query()
            ->where('play_id', $spin->play_id)
            ->exists();

        if ($exists) {
            return;
        }

        $result = $spin->reward_amount > 0 ? 'won' : 'lost';

        GameHistory::query()->create([
            'user_id' => $spin->user_id,
            'game_id' => $spin->game_id,
            'play_id' => $spin->play_id,
            'result' => $result,
            'bet_coins' => $spin->cost_coins,
            'reward_coins' => $spin->reward_currency === 'coins' ? $spin->reward_amount : 0,
            'snapshot' => [
                'spin_id' => $spin->id,
                'sector_position' => $spin->sector_position,
                'sector_label' => $spin->sector_label,
                'reward_type' => $spin->reward_type,
                'reward_amount' => $spin->reward_amount,
                'is_jackpot' => $spin->is_jackpot,
                'outcome' => $spin->outcome,
            ],
            'created_at' => now(),
        ]);
    }
}
