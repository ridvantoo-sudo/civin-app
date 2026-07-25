<?php

namespace App\Features\Games\Plugins\LuckyNumber\Listeners;

use App\Features\Games\Plugins\LuckyNumber\Events\LuckyNumberDrawFinished;
use App\Features\Games\Plugins\LuckyNumber\Repositories\Contracts\LuckyNumberRepository;

final class UpdateLuckyNumberStatistics
{
    public function __construct(private readonly LuckyNumberRepository $luckyNumbers) {}

    public function handle(LuckyNumberDrawFinished $event): void
    {
        $ticket = $event->ticket;
        $existing = $this->luckyNumbers->statistics($ticket->user, $ticket->config_id);

        $ticketsPurchased = (int) ($existing?->tickets_purchased ?? 0) + 1;
        $gamesPlayed = (int) ($existing?->games_played ?? 0) + 1;
        $wagered = (int) ($existing?->coins_wagered ?? 0) + (int) $ticket->cost_coins;
        $wonCoins = (int) ($existing?->coins_won ?? 0) + (int) $ticket->reward_coins;
        $diamonds = (int) ($existing?->diamonds_won ?? 0) + (int) $ticket->reward_diamonds;
        $jackpots = (int) ($existing?->jackpots_won ?? 0) + ($ticket->is_jackpot ? 1 : 0);
        $highest = max((int) ($existing?->highest_win ?? 0), (int) $ticket->reward_coins);
        $average = $gamesPlayed > 0 ? round($wonCoins / $gamesPlayed, 2) : 0;

        $priorWins = (int) round(((float) ($existing?->win_percentage ?? 0) / 100) * max(0, $gamesPlayed - 1));
        $wins = $priorWins + (($ticket->is_win || $ticket->is_jackpot) ? 1 : 0);
        $winPercentage = $gamesPlayed > 0 ? round(($wins / $gamesPlayed) * 100, 4) : 0;

        $currentStreak = ($ticket->is_win || $ticket->is_jackpot)
            ? (int) ($existing?->current_streak ?? 0) + 1
            : 0;
        $bestStreak = max((int) ($existing?->best_streak ?? 0), $currentStreak);

        $this->luckyNumbers->upsertStatistics($ticket->user, $ticket->config_id, [
            'tickets_purchased' => $ticketsPurchased,
            'games_played' => $gamesPlayed,
            'coins_wagered' => $wagered,
            'coins_won' => $wonCoins,
            'diamonds_won' => $diamonds,
            'jackpots_won' => $jackpots,
            'highest_win' => $highest,
            'average_win' => $average,
            'win_percentage' => $winPercentage,
            'current_streak' => $currentStreak,
            'best_streak' => $bestStreak,
        ]);
    }
}
