<?php

namespace App\Features\Games\Engines;

use App\Features\Games\Models\Game;
use App\Features\Users\Models\User;

final readonly class EngineOutcome
{
    /**
     * @param  array<string, mixed>  $outcome
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        public int $betCoins,
        public int $rewardCoins,
        public int $rewardDiamonds,
        public string $result,
        public array $outcome,
        public array $input = [],
    ) {}
}

interface GameEngine
{
    public function slug(): string;

    public function resolve(User $user, Game $game, int $betCoins, array $input): EngineOutcome;
}
