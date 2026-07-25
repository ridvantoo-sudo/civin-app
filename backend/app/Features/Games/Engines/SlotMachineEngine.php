<?php

namespace App\Features\Games\Engines;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Services\GameRng;
use App\Features\Users\Models\User;

final class SlotMachineEngine implements GameEngine
{
    public function __construct(private readonly GameRng $rng) {}

    public function slug(): string
    {
        return Game::SLUG_SLOT_MACHINE;
    }

    public function resolve(User $user, Game $game, int $betCoins, array $input): EngineOutcome
    {
        $symbols = $game->config['symbols'] ?? ['cherry', 'lemon', 'bell', 'star', 'seven'];
        $reels = [
            $symbols[$this->rng->int(0, count($symbols) - 1)],
            $symbols[$this->rng->int(0, count($symbols) - 1)],
            $symbols[$this->rng->int(0, count($symbols) - 1)],
        ];

        $multiplier = 0;
        if ($reels[0] === $reels[1] && $reels[1] === $reels[2]) {
            $multiplier = $reels[0] === 'seven' ? 20 : 8;
        } elseif ($reels[0] === $reels[1] || $reels[1] === $reels[2] || $reels[0] === $reels[2]) {
            $multiplier = 2;
        }

        $reward = (int) floor($betCoins * $multiplier);
        $result = $reward > $betCoins
            ? GamePlay::RESULT_WON
            : ($reward === $betCoins && $multiplier === 1 ? GamePlay::RESULT_DRAW : GamePlay::RESULT_LOST);

        if ($multiplier === 0) {
            $result = GamePlay::RESULT_LOST;
        } elseif ($multiplier >= 2) {
            $result = GamePlay::RESULT_WON;
        }

        return new EngineOutcome(
            betCoins: $betCoins,
            rewardCoins: $reward,
            rewardDiamonds: $multiplier >= 20 ? 2 : 0,
            result: $result,
            outcome: [
                'reels' => $reels,
                'multiplier' => $multiplier,
                'animation' => 'slot_spin',
            ],
            input: $input,
        );
    }
}
