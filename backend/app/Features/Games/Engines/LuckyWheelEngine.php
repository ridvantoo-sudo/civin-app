<?php

namespace App\Features\Games\Engines;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Services\GameRng;
use App\Features\Users\Models\User;
use Illuminate\Validation\ValidationException;

final class LuckyWheelEngine implements GameEngine
{
    public function __construct(private readonly GameRng $rng) {}

    public function slug(): string
    {
        return Game::SLUG_LUCKY_WHEEL;
    }

    public function resolve(User $user, Game $game, int $betCoins, array $input): EngineOutcome
    {
        $segments = $game->config['segments'] ?? [
            ['label' => 'x0', 'multiplier' => 0, 'weight' => 40],
            ['label' => 'x1', 'multiplier' => 1, 'weight' => 30],
            ['label' => 'x2', 'multiplier' => 2, 'weight' => 18],
            ['label' => 'x5', 'multiplier' => 5, 'weight' => 8],
            ['label' => 'x10', 'multiplier' => 10, 'weight' => 4],
        ];

        $totalWeight = array_sum(array_map(fn ($s) => (int) ($s['weight'] ?? 0), $segments));
        if ($totalWeight < 1) {
            throw ValidationException::withMessages(['game' => 'Lucky Wheel is misconfigured.']);
        }

        $roll = $this->rng->int(1, $totalWeight);
        $cursor = 0;
        $selected = $segments[0];
        $index = 0;
        foreach ($segments as $i => $segment) {
            $cursor += (int) ($segment['weight'] ?? 0);
            if ($roll <= $cursor) {
                $selected = $segment;
                $index = (int) $i;
                break;
            }
        }

        $multiplier = (int) ($selected['multiplier'] ?? 0);
        $reward = (int) floor($betCoins * $multiplier);
        $result = $reward > $betCoins
            ? GamePlay::RESULT_WON
            : ($reward === $betCoins ? GamePlay::RESULT_DRAW : GamePlay::RESULT_LOST);

        return new EngineOutcome(
            betCoins: $betCoins,
            rewardCoins: $reward,
            rewardDiamonds: 0,
            result: $result,
            outcome: [
                'segment_index' => $index,
                'label' => $selected['label'] ?? (string) $multiplier,
                'multiplier' => $multiplier,
                'animation' => 'wheel_spin',
            ],
            input: $input,
        );
    }
}
