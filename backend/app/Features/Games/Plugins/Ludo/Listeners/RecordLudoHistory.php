<?php

namespace App\Features\Games\Plugins\Ludo\Listeners;

use App\Features\Games\Plugins\Ludo\Events\LudoMatchFinished;
use App\Features\Games\Plugins\Ludo\Models\LudoHistory;
use App\Features\Games\Plugins\Ludo\Models\LudoMatch;

final class RecordLudoHistory
{
    public function handle(LudoMatchFinished $event): void
    {
        if (! $event->subject instanceof LudoMatch) {
            return;
        }
        $match = $event->subject;
        foreach ($match->players()->where('is_spectator', false)->get() as $player) {
            LudoHistory::query()->firstOrCreate(
                ['user_id' => $player->user_id, 'match_id' => $match->getKey()],
                [
                    'tournament_id' => $match->tournament_id, 'mode' => $match->mode,
                    'result' => (string) $match->result,
                    'is_winner' => (string) $match->winner_id === (string) $player->user_id,
                    'captures' => $player->captures, 'pieces_finished' => $player->pieces_finished,
                    'entry_fee_coins' => $match->entry_fee_coins,
                    'reward_coins' => (string) $match->winner_id === (string) $player->user_id ? max(0, $match->pot_coins - $match->rake_coins) : 0,
                    'snapshot' => $match->board_state,
                ],
            );
        }
    }
}
