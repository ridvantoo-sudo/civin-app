<?php

namespace App\Features\Games\Plugins\LuckyNumber\Listeners;

use App\Features\Games\Models\GameHistory;
use App\Features\Games\Plugins\LuckyNumber\Events\LuckyNumberDrawFinished;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberHistory;

final class RecordLuckyNumberHistory
{
    public function handle(LuckyNumberDrawFinished $event): void
    {
        $ticket = $event->ticket;
        if ($ticket->game_id === null) {
            return;
        }

        $exists = GameHistory::query()->where('play_id', $ticket->play_id)->exists();
        if (! $exists && $ticket->play_id !== null) {
            GameHistory::query()->create([
                'user_id' => $ticket->user_id,
                'game_id' => $ticket->game_id,
                'play_id' => $ticket->play_id,
                'result' => $ticket->is_win ? 'won' : 'lost',
                'bet_coins' => $ticket->cost_coins,
                'reward_coins' => $ticket->reward_coins,
                'snapshot' => [
                    'ticket_id' => $ticket->id,
                    'draw_id' => $ticket->draw_id,
                    'game_mode' => $ticket->game_mode,
                    'picked_numbers' => $ticket->picked_numbers,
                    'is_jackpot' => $ticket->is_jackpot,
                    'is_bonus' => $ticket->is_bonus,
                    'outcome' => $ticket->outcome,
                ],
                'created_at' => now(),
            ]);
        }

        LuckyNumberHistory::query()->firstOrCreate(
            ['ticket_id' => $ticket->id],
            [
                'user_id' => $ticket->user_id,
                'config_id' => $ticket->config_id,
                'game_id' => $ticket->game_id,
                'play_id' => $ticket->play_id,
                'game_mode' => $ticket->game_mode,
                'result' => $ticket->is_win ? 'won' : 'lost',
                'bet_coins' => $ticket->cost_coins,
                'reward_coins' => $ticket->reward_coins,
                'reward_diamonds' => $ticket->reward_diamonds,
                'is_jackpot' => $ticket->is_jackpot,
                'is_bonus' => $ticket->is_bonus,
                'snapshot' => $ticket->outcome,
            ],
        );
    }
}
