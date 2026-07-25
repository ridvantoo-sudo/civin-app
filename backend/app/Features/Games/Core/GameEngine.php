<?php

namespace App\Features\Games\Core;

use App\Features\Games\Engines\EngineOutcome;
use App\Features\Games\Models\Game;
use App\Features\Users\Models\User;

/**
 * Closed engine facade. New games register plugins — this class is not edited.
 */
final readonly class GameEngine
{
    public function __construct(private GameManager $manager) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function resolve(User $user, Game $game, int $betCoins, array $input): EngineOutcome
    {
        return $this->manager->resolve($user, $game, $betCoins, $input);
    }
}
