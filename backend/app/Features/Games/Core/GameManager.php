<?php

namespace App\Features\Games\Core;

use App\Features\Games\Core\Contracts\GamePlugin;
use App\Features\Games\Engines\EngineOutcome;
use App\Features\Games\Models\Game;
use App\Features\Users\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Closed orchestrator — plugins provide game-specific logic.
 */
final readonly class GameManager
{
    public function __construct(private GameRegistry $registry) {}

    public function registry(): GameRegistry
    {
        return $this->registry;
    }

    public function plugin(string $slug): GamePlugin
    {
        return $this->registry->require($slug);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function resolve(User $user, Game $game, int $betCoins, array $input): EngineOutcome
    {
        $plugin = $this->registry->get($game->slug);
        if ($plugin === null) {
            throw ValidationException::withMessages([
                'game_slug' => 'No plugin registered for this game.',
            ]);
        }

        $outcome = $plugin->resolve($user, $game, $betCoins, $input);
        if ($outcome === null) {
            throw ValidationException::withMessages([
                'game_slug' => 'This game requires a dedicated session flow.',
            ]);
        }

        return $outcome;
    }
}
