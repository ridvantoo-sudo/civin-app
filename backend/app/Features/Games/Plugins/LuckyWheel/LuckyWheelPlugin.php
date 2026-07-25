<?php

namespace App\Features\Games\Plugins\LuckyWheel;

use App\Features\Games\Core\Contracts\GamePlugin;
use App\Features\Games\Engines\EngineOutcome;
use App\Features\Games\Engines\LuckyWheelEngine;
use App\Features\Games\Models\Game;
use App\Features\Users\Models\User;

/**
 * Lucky Wheel plugin — generic engine resolve remains for /games/play.
 * Dedicated production spin flow lives in LuckyWheelService.
 */
final class LuckyWheelPlugin implements GamePlugin
{
    public function __construct(private readonly LuckyWheelEngine $engine) {}

    public function slug(): string
    {
        return Game::SLUG_LUCKY_WHEEL;
    }

    public function displayName(): string
    {
        return 'Lucky Wheel';
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
            'spin_cost_coins' => 100,
            'daily_free_spins' => 0,
            'cooldown_seconds' => 0,
            'jackpot_enabled' => true,
            'sectors' => [
                ['position' => 0, 'label' => 'Try again', 'reward_type' => 'none', 'reward_amount' => 0, 'weight' => 30],
                ['position' => 1, 'label' => '50 coins', 'reward_type' => 'coins', 'reward_amount' => 50, 'weight' => 25],
                ['position' => 2, 'label' => '100 coins', 'reward_type' => 'coins', 'reward_amount' => 100, 'weight' => 20],
                ['position' => 3, 'label' => '200 coins', 'reward_type' => 'coins', 'reward_amount' => 200, 'weight' => 12],
                ['position' => 4, 'label' => '5 diamonds', 'reward_type' => 'diamonds', 'reward_amount' => 5, 'weight' => 8],
                ['position' => 5, 'label' => '500 coins', 'reward_type' => 'coins', 'reward_amount' => 500, 'weight' => 4],
                ['position' => 6, 'label' => 'Jackpot', 'reward_type' => 'coins', 'reward_amount' => 5000, 'weight' => 1, 'is_jackpot' => true],
            ],
        ];
    }
}
