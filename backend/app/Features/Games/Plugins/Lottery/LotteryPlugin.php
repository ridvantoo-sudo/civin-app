<?php

namespace App\Features\Games\Plugins\Lottery;

use App\Features\Games\Core\Contracts\GamePlugin;
use App\Features\Games\Engines\EngineOutcome;
use App\Features\Games\Models\Game;
use App\Features\Users\Models\User;

final class LotteryPlugin implements GamePlugin
{
    public function slug(): string
    {
        return Game::SLUG_LOTTERY;
    }

    public function displayName(): string
    {
        return 'Lottery';
    }

    public function supportsMultiplayer(): bool
    {
        return false;
    }

    public function resolve(User $user, Game $game, int $betCoins, array $input): ?EngineOutcome
    {
        // Handled by GameService dedicated session flows.
        return null;
    }

    public function defaultConfig(): array
    {
        return [];
    }
}
