<?php

namespace App\Features\Games\Actions;

use App\Features\Games\Models\GameMatch;
use App\Features\Games\Services\GameService;

final readonly class GetGameMatch
{
    public function __construct(private GameService $games) {}

    public function execute(string $matchId): GameMatch
    {
        return $this->games->getMatch($matchId);
    }
}
