<?php

namespace App\Features\Games\Engines;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Services\GameRng;
use App\Features\Users\Models\User;
use Illuminate\Validation\ValidationException;

final class RockPaperScissorsEngine implements GameEngine
{
    private const CHOICES = ['rock', 'paper', 'scissors'];

    public function __construct(private readonly GameRng $rng) {}

    public function slug(): string
    {
        return Game::SLUG_ROCK_PAPER_SCISSORS;
    }

    public function resolve(User $user, Game $game, int $betCoins, array $input): EngineOutcome
    {
        $choice = strtolower((string) ($input['choice'] ?? ''));
        if (! in_array($choice, self::CHOICES, true)) {
            throw ValidationException::withMessages([
                'input.choice' => 'Choice must be rock, paper, or scissors.',
            ]);
        }

        $house = self::CHOICES[$this->rng->int(0, 2)];
        $result = $this->compare($choice, $house);
        $reward = match ($result) {
            GamePlay::RESULT_WON => $betCoins * 2,
            GamePlay::RESULT_DRAW => $betCoins,
            default => 0,
        };

        return new EngineOutcome(
            betCoins: $betCoins,
            rewardCoins: $reward,
            rewardDiamonds: 0,
            result: $result,
            outcome: [
                'player' => $choice,
                'house' => $house,
                'animation' => 'rps_reveal',
            ],
            input: ['choice' => $choice],
        );
    }

    private function compare(string $player, string $house): string
    {
        if ($player === $house) {
            return GamePlay::RESULT_DRAW;
        }

        $wins = [
            'rock' => 'scissors',
            'paper' => 'rock',
            'scissors' => 'paper',
        ];

        return $wins[$player] === $house ? GamePlay::RESULT_WON : GamePlay::RESULT_LOST;
    }
}
