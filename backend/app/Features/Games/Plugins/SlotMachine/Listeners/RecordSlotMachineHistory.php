<?php

namespace App\Features\Games\Plugins\SlotMachine\Listeners;

use App\Features\Games\Models\GameHistory;
use App\Features\Games\Plugins\SlotMachine\Events\SlotSpinFinished;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineHistory;

final class RecordSlotMachineHistory
{
    public function handle(SlotSpinFinished $event): void
    {
        $spin = $event->spin;
        if ($spin->game_id === null) {
            return;
        }

        $exists = GameHistory::query()->where('play_id', $spin->play_id)->exists();
        if (! $exists) {
            GameHistory::query()->create([
                'user_id' => $spin->user_id,
                'game_id' => $spin->game_id,
                'play_id' => $spin->play_id,
                'result' => $spin->is_win ? 'won' : 'lost',
                'bet_coins' => $spin->cost_coins,
                'reward_coins' => $spin->reward_coins,
                'snapshot' => [
                    'spin_id' => $spin->id,
                    'game_mode' => $spin->game_mode,
                    'is_jackpot' => $spin->is_jackpot,
                    'is_bonus' => $spin->is_bonus,
                    'outcome' => $spin->outcome,
                ],
                'created_at' => now(),
            ]);
        }

        SlotMachineHistory::query()->firstOrCreate(
            ['spin_id' => $spin->id],
            [
                'user_id' => $spin->user_id,
                'config_id' => $spin->config_id,
                'game_id' => $spin->game_id,
                'play_id' => $spin->play_id,
                'game_mode' => $spin->game_mode,
                'result' => $spin->is_win ? 'won' : 'lost',
                'bet_coins' => $spin->cost_coins,
                'reward_coins' => $spin->reward_coins,
                'reward_diamonds' => $spin->reward_diamonds,
                'is_jackpot' => $spin->is_jackpot,
                'is_bonus' => $spin->is_bonus,
                'snapshot' => $spin->outcome,
            ],
        );
    }
}
