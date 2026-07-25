<?php

namespace App\Features\Games\Plugins\LuckyNumber\Listeners;

use App\Features\Games\Models\Game;
use App\Features\Games\Plugins\LuckyNumber\Events\LuckyNumberDrawFinished;
use App\Features\Games\Plugins\LuckyNumber\Events\LuckyNumberJackpotWon;
use App\Features\Games\Plugins\LuckyNumber\Events\LuckyNumberTicketPurchased;
use App\Features\Games\Plugins\LuckyNumber\Events\LuckyNumberWon;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberTicket;
use App\Features\Games\Repositories\Contracts\GameRepository;

final class RecordLuckyNumberAnalytics
{
    public function __construct(private readonly GameRepository $games) {}

    public function handle(object $event): void
    {
        if (! property_exists($event, 'ticket') || ! $event->ticket instanceof LuckyNumberTicket) {
            return;
        }

        $ticket = $event->ticket;
        $game = $ticket->game_id !== null
            ? Game::query()->find($ticket->game_id)
            : Game::query()->where('slug', Game::SLUG_LUCKY_NUMBER)->first();

        $payload = [
            'ticket_id' => $ticket->id,
            'draw_id' => $ticket->draw_id,
            'idempotency_key' => $ticket->idempotency_key,
            'game_mode' => $ticket->game_mode,
            'cost_coins' => $ticket->cost_coins,
            'reward_coins' => $ticket->reward_coins,
            'is_jackpot' => $ticket->is_jackpot,
            'is_bonus' => $ticket->is_bonus,
        ];

        if ($event instanceof LuckyNumberTicketPurchased) {
            $this->games->trackAnalytics($ticket->user, $game, 'LuckyNumberTicketPurchased', $payload);

            return;
        }

        if ($event instanceof LuckyNumberJackpotWon) {
            $this->games->trackAnalytics($ticket->user, $game, 'LuckyNumberJackpotWon', $payload);

            return;
        }

        if ($event instanceof LuckyNumberWon) {
            $this->games->trackAnalytics($ticket->user, $game, 'LuckyNumberWon', $payload);

            return;
        }

        if ($event instanceof LuckyNumberDrawFinished) {
            $this->games->trackAnalytics($ticket->user, $game, 'LuckyNumberPlayed', $payload);
            $this->games->trackAnalytics($ticket->user, $game, 'LuckyNumberDrawCompleted', $payload);
            if (! $ticket->is_win && ! $ticket->is_jackpot) {
                $this->games->trackAnalytics($ticket->user, $game, 'LuckyNumberLost', $payload);
            }
        }
    }
}
