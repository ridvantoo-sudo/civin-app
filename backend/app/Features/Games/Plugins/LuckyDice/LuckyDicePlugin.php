<?php

namespace App\Features\Games\Plugins\LuckyDice;

use App\Features\Games\Core\Contracts\GamePlugin;
use App\Features\Games\Engines\EngineOutcome;
use App\Features\Games\Engines\LuckyDiceEngine;
use App\Features\Games\Models\Game;
use App\Features\Users\Models\User;

/**
 * Lucky Dice plugin — generic engine resolve remains for /games/play.
 * Dedicated production play flow lives in LuckyDiceService.
 */
final class LuckyDicePlugin implements GamePlugin
{
    public function __construct(private readonly LuckyDiceEngine $engine) {}

    public function slug(): string
    {
        return Game::SLUG_LUCKY_DICE;
    }

    public function displayName(): string
    {
        return 'Lucky Dice';
    }

    public function supportsMultiplayer(): bool
    {
        return false;
    }

    public function resolve(User $user, Game $game, int $betCoins, array $input): ?EngineOutcome
    {
        return $this->engine->resolve($user, $game, $betCoins, $input);
    }

    public function defaultConfig(): array
    {
        return [
            'min_bet_coins' => 50,
            'max_bet_coins' => 10000,
            'default_bet_coins' => 100,
            'chip_amounts' => [50, 100, 200, 500, 1000],
            'max_plays_per_day' => 200,
            'cooldown_seconds' => 0,
            'countdown_seconds' => 5,
            'multiplier_big_small' => 2,
            'multiplier_odd_even' => 2,
            'multiplier_triple' => 30,
            'exact_total_multipliers' => [
                '3' => 50,
                '4' => 30,
                '5' => 18,
                '6' => 12,
                '7' => 8,
                '8' => 6,
                '9' => 4,
                '10' => 3,
                '11' => 3,
                '12' => 4,
                '13' => 6,
                '14' => 8,
                '15' => 12,
                '16' => 18,
                '17' => 30,
                '18' => 50,
            ],
            'jackpot_enabled' => true,
            'jackpot_coins' => 10000,
            'jackpot_on_triple' => true,
            'modes_enabled' => ['big_small', 'odd_even', 'exact_total', 'triple_match'],
        ];
    }
}
