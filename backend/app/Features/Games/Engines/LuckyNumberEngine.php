<?php

namespace App\Features\Games\Engines;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Services\GameRng;
use App\Features\Users\Models\User;
use Illuminate\Validation\ValidationException;

final class LuckyNumberEngine implements GameEngine
{
    public function __construct(private readonly GameRng $rng) {}

    public function slug(): string
    {
        return Game::SLUG_LUCKY_NUMBER;
    }

    public function resolve(User $user, Game $game, int $betCoins, array $input): EngineOutcome
    {
        $max = (int) ($game->config['max_number'] ?? 10);
        $number = isset($input['number']) ? (int) $input['number'] : 0;
        if ($number < 1 || $number > $max) {
            throw ValidationException::withMessages([
                'input.number' => "Pick a number between 1 and {$max}.",
            ]);
        }

        $drawn = $this->rng->int(1, $max);
        $won = $number === $drawn;
        $multiplier = $won ? max(2, $max - 1) : 0;
        $reward = (int) floor($betCoins * $multiplier);

        return new EngineOutcome(
            betCoins: $betCoins,
            rewardCoins: $reward,
            rewardDiamonds: 0,
            result: $won ? GamePlay::RESULT_WON : GamePlay::RESULT_LOST,
            outcome: [
                'drawn' => $drawn,
                'picked' => $number,
                'won' => $won,
                'multiplier' => $multiplier,
                'animation' => 'number_reveal',
            ],
            input: ['number' => $number],
        );
    }
}
