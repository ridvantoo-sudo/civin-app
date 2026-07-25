<?php

namespace App\Features\Games\Core\Services;

use App\Features\Games\DTOs\CreateMatchData;
use App\Features\Games\DTOs\MatchActionData;
use App\Features\Games\Models\GameMatch;
use App\Features\Games\Services\GameService;
use App\Features\Users\Models\User;

final readonly class GameSessionService
{
    public function __construct(private GameService $games) {}

    public function createMatch(User $user, CreateMatchData $data): GameMatch
    {
        return $this->games->createMatch($user, $data);
    }

    public function joinMatch(User $user, string $matchId): GameMatch
    {
        return $this->games->joinMatch($user, $matchId);
    }

    public function matchAction(User $user, string $matchId, MatchActionData $data): GameMatch
    {
        return $this->games->matchAction($user, $matchId, $data);
    }

    public function getMatch(string $matchId): GameMatch
    {
        return $this->games->getMatch($matchId);
    }
}
