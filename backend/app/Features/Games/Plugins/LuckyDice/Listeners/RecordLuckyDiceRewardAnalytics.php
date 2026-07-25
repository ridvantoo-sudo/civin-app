<?php

namespace App\Features\Games\Plugins\LuckyDice\Listeners;

use App\Features\Games\Models\Game;
use App\Features\Games\Plugins\LuckyDice\Events\LuckyDiceJackpotWon;
use App\Features\Games\Repositories\Contracts\GameRepository;

final class RecordLuckyDiceRewardAnalytics
{
    public function __construct(private readonly GameRepository $games) {}

    public function handle(LuckyDiceJackpotWon $event): void
    {
        $bet = $event->bet;
        $game = $bet->game_id !== null
            ? Game::query()->find($bet->game_id)
            : Game::query()->where('slug', Game::SLUG_LUCKY_DICE)->first();

        $this->games->trackAnalytics($bet->user, $game, 'lucky_dice_jackpot_won', [
            'bet_id' => $bet->id,
            'reward_type' => $bet->reward_type,
            'reward_amount' => $bet->reward_amount,
            'reward_currency' => $bet->reward_currency,
            'is_jackpot' => $bet->is_jackpot,
        ]);
    }
}
