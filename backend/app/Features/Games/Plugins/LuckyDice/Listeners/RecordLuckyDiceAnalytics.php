<?php

namespace App\Features\Games\Plugins\LuckyDice\Listeners;

use App\Features\Games\Models\Game;
use App\Features\Games\Plugins\LuckyDice\Events\LuckyDiceLost;
use App\Features\Games\Plugins\LuckyDice\Events\LuckyDicePlayed;
use App\Features\Games\Plugins\LuckyDice\Events\LuckyDiceWon;
use App\Features\Games\Repositories\Contracts\GameRepository;

final class RecordLuckyDiceAnalytics
{
    public function __construct(private readonly GameRepository $games) {}

    public function handle(LuckyDicePlayed|LuckyDiceWon|LuckyDiceLost $event): void
    {
        $bet = $event->bet;
        $game = $bet->game_id !== null
            ? Game::query()->find($bet->game_id)
            : Game::query()->where('slug', Game::SLUG_LUCKY_DICE)->first();

        $eventName = match (true) {
            $event instanceof LuckyDiceWon => 'lucky_dice_won',
            $event instanceof LuckyDiceLost => 'lucky_dice_lost',
            default => 'lucky_dice_played',
        };

        $this->games->trackAnalytics($bet->user, $game, $eventName, [
            'bet_id' => $bet->id,
            'idempotency_key' => $bet->idempotency_key,
            'bet_mode' => $bet->bet_mode,
            'prediction' => $bet->prediction,
            'bet_amount' => $bet->bet_amount,
            'reward_type' => $bet->reward_type,
            'reward_amount' => $bet->reward_amount,
            'is_jackpot' => $bet->is_jackpot,
            'is_win' => $bet->is_win,
            'multiplier' => $bet->multiplier,
        ]);
    }
}
