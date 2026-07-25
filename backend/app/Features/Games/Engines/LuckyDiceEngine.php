<?php

namespace App\Features\Games\Engines;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Services\GameRng;
use App\Features\Users\Models\User;
use Illuminate\Validation\ValidationException;

final class LuckyDiceEngine implements GameEngine
{
    public function __construct(private readonly GameRng $rng) {}

    public function slug(): string
    {
        return Game::SLUG_LUCKY_DICE;
    }

    public function resolve(User $user, Game $game, int $betCoins, array $input): EngineOutcome
    {
        $guess = isset($input['guess']) ? (int) $input['guess'] : null;
        $prediction = isset($input['prediction']) ? (string) $input['prediction'] : null;

        if ($guess === null && $prediction === null) {
            throw ValidationException::withMessages([
                'input.guess' => 'Provide a dice guess (1-6) or prediction (high/low).',
            ]);
        }

        if ($guess !== null && ($guess < 1 || $guess > 6)) {
            throw ValidationException::withMessages(['input.guess' => 'Guess must be between 1 and 6.']);
        }

        if ($prediction !== null && ! in_array($prediction, ['high', 'low'], true)) {
            throw ValidationException::withMessages(['input.prediction' => 'Prediction must be high or low.']);
        }

        $roll = $this->rng->int(1, 6);
        $won = false;
        $multiplier = 0;

        if ($guess !== null) {
            $won = $guess === $roll;
            $multiplier = $won ? 5 : 0;
        } else {
            $won = $prediction === 'high' ? $roll >= 4 : $roll <= 3;
            $multiplier = $won ? 2 : 0;
        }

        $reward = (int) floor($betCoins * $multiplier);

        return new EngineOutcome(
            betCoins: $betCoins,
            rewardCoins: $reward,
            rewardDiamonds: 0,
            result: $won ? GamePlay::RESULT_WON : GamePlay::RESULT_LOST,
            outcome: [
                'roll' => $roll,
                'won' => $won,
                'multiplier' => $multiplier,
                'animation' => 'dice_roll',
            ],
            input: [
                'guess' => $guess,
                'prediction' => $prediction,
            ],
        );
    }
}
