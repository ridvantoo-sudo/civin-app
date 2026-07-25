<?php

namespace App\Features\Games\Core\Services;

use App\Features\Games\Models\Game;
use Illuminate\Validation\ValidationException;

final readonly class GamePermissionService
{
    public function canPlay(Game $game): bool
    {
        return $game->isActive();
    }

    public function assertActive(Game $game): void
    {
        if (! $game->isActive()) {
            throw ValidationException::withMessages(['game' => 'Game is inactive.']);
        }
    }
}
