<?php

namespace App\Features\Games\Plugins\SlotMachine;

use App\Features\Games\Core\Contracts\GamePlugin;
use App\Features\Games\Engines\EngineOutcome;
use App\Features\Games\Engines\SlotMachineEngine;
use App\Features\Games\Models\Game;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineConfig;
use App\Features\Users\Models\User;

/**
 * Slot Machine plugin — generic engine resolve remains for /games/play.
 * Dedicated production play flow lives in SlotMachineService.
 */
final class SlotMachinePlugin implements GamePlugin
{
    public function __construct(private readonly SlotMachineEngine $engine) {}

    public function slug(): string
    {
        return Game::SLUG_SLOT_MACHINE;
    }

    public function displayName(): string
    {
        return 'Slot Machine';
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
            'min_bet_coins' => 10,
            'max_bet_coins' => 10000,
            'default_bet_coins' => 50,
            'chip_amounts' => [10, 50, 100, 500, 1000],
            'modes_enabled' => SlotMachineConfig::MODES,
            'rtp_percent' => 96.0,
            'house_edge_percent' => 4.0,
            'classic_columns' => 3,
            'classic_rows' => 3,
            'premium_columns' => 5,
            'premium_rows' => 3,
            'jackpot_enabled' => true,
            'bonus_enabled' => true,
            'symbols' => [
                ['code' => 'cherry', 'label' => 'Cherry', 'weight' => 30, 'payout_3' => 2],
                ['code' => 'seven', 'label' => 'Seven', 'weight' => 8, 'payout_3' => 20],
                ['code' => 'wild', 'label' => 'Wild', 'weight' => 5, 'is_wild' => true],
                ['code' => 'scatter', 'label' => 'Scatter', 'weight' => 4, 'is_scatter' => true],
                ['code' => 'jackpot', 'label' => 'Jackpot', 'weight' => 1, 'is_jackpot' => true],
                ['code' => 'bonus', 'label' => 'Bonus', 'weight' => 3, 'is_bonus' => true],
            ],
        ];
    }
}
