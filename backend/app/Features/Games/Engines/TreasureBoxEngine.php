<?php

namespace App\Features\Games\Engines;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Services\GameRng;
use App\Features\Users\Models\User;
use Illuminate\Validation\ValidationException;

final class TreasureBoxEngine implements GameEngine
{
    public function __construct(private readonly GameRng $rng) {}

    public function slug(): string
    {
        return Game::SLUG_TREASURE_BOX;
    }

    public function resolve(User $user, Game $game, int $betCoins, array $input): EngineOutcome
    {
        $tiers = $game->config['tiers'] ?? [
            'bronze' => ['cost_multiplier' => 1, 'min_mult' => 0, 'max_mult' => 2],
            'silver' => ['cost_multiplier' => 2, 'min_mult' => 0, 'max_mult' => 4],
            'gold' => ['cost_multiplier' => 5, 'min_mult' => 0, 'max_mult' => 10],
        ];

        $tier = (string) ($input['box_tier'] ?? 'bronze');
        if (! isset($tiers[$tier])) {
            throw ValidationException::withMessages(['input.box_tier' => 'Invalid treasure box tier.']);
        }

        $cfg = $tiers[$tier];
        $cost = max($betCoins, (int) floor($game->min_bet_coins * (int) ($cfg['cost_multiplier'] ?? 1)));
        if ($betCoins > 0) {
            $cost = $betCoins * max(1, (int) ($cfg['cost_multiplier'] ?? 1));
        }

        $minMult = (int) ($cfg['min_mult'] ?? 0);
        $maxMult = (int) ($cfg['max_mult'] ?? 2);
        $mult = $this->rng->int($minMult, max($minMult, $maxMult));
        $reward = (int) floor($cost * $mult);
        $diamonds = $mult >= $maxMult ? 1 : 0;

        $result = $reward > $cost
            ? GamePlay::RESULT_WON
            : ($reward === $cost ? GamePlay::RESULT_DRAW : GamePlay::RESULT_LOST);

        return new EngineOutcome(
            betCoins: $cost,
            rewardCoins: $reward,
            rewardDiamonds: $diamonds,
            result: $result,
            outcome: [
                'tier' => $tier,
                'multiplier' => $mult,
                'diamonds' => $diamonds,
                'animation' => 'box_open',
            ],
            input: ['box_tier' => $tier],
        );
    }
}
