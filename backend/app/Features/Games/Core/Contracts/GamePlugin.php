<?php

namespace App\Features\Games\Core\Contracts;

use App\Features\Games\Engines\EngineOutcome;
use App\Features\Games\Models\Game;
use App\Features\Users\Models\User;

/**
 * Isolated game plugin contract.
 *
 * Adding a game = create a plugin + register it. GameEngine/GameManager stay closed.
 */
interface GamePlugin
{
    public function slug(): string;

    public function displayName(): string;

    public function supportsMultiplayer(): bool;

    /**
     * Solo/instant resolve. Return null if the game uses custom session flows.
     *
     * @param  array<string, mixed>  $input
     */
    public function resolve(User $user, Game $game, int $betCoins, array $input): ?EngineOutcome;

    /** @return array<string, mixed> */
    public function defaultConfig(): array;
}
