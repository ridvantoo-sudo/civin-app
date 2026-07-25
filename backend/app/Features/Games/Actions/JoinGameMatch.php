<?php

namespace App\Features\Games\Actions;

use App\Features\Games\Models\GameMatch;
use App\Features\Games\Services\GameService;
use App\Features\Users\Models\User;

final readonly class JoinGameMatch
{
    public function __construct(private GameService $games) {}

    public function execute(User $user, string $matchId): GameMatch
    {
        return $this->games->joinMatch($user, $matchId);
    }
}
