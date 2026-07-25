<?php

namespace App\Features\Games\Plugins\Ludo\Listeners;

use App\Features\Games\Plugins\Ludo\Events\LudoMatchFinished;
use App\Features\Games\Plugins\Ludo\Models\LudoMatch;
use App\Features\Games\Plugins\Ludo\Models\LudoStatistic;

final class UpdateLudoStatistics
{
    public function handle(LudoMatchFinished $event): void
    {
        if (! $event->subject instanceof LudoMatch) {
            return;
        }
        foreach ($event->subject->players()->where('is_spectator', false)->get() as $player) {
            $won = (string) $event->subject->winner_id === (string) $player->user_id;
            $stat = LudoStatistic::query()->firstOrCreate(['user_id' => $player->user_id]);
            $stat->increment('matches_played');
            $stat->increment($won ? 'matches_won' : 'matches_lost');
            $stat->increment('captures', $player->captures);
            $stat->increment('pieces_finished', $player->pieces_finished);
            $stat->increment('coins_wagered', $event->subject->entry_fee_coins);
            if ($won) {
                $stat->increment('coins_won', max(0, $event->subject->pot_coins - $event->subject->rake_coins));
                $stat->increment('current_streak');
                $stat->update(['best_streak' => max($stat->best_streak, $stat->current_streak)]);
            } else {
                $stat->update(['current_streak' => 0]);
            }
        }
    }
}
