<?php

namespace App\Features\Games\Actions;

use App\Features\Games\DTOs\MatchActionData;
use App\Features\Games\Models\GameMatch;
use App\Features\Games\Services\GameService;
use App\Features\Users\Models\User;

final readonly class PerformMatchAction
{
    public function __construct(private GameService $games) {}

    public function execute(User $user, string $matchId, MatchActionData $data): GameMatch
    {
        return $this->games->matchAction($user, $matchId, $data);
    }
}
