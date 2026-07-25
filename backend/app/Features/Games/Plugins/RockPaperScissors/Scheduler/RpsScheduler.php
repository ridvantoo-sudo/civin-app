<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Scheduler;

use App\Features\Games\Plugins\RockPaperScissors\Services\RpsMatchService;
use App\Features\Games\Plugins\RockPaperScissors\Services\RpsTournamentService;

final readonly class RpsScheduler
{
    public function __construct(
        private RpsMatchService $matches,
        private RpsTournamentService $tournaments,
    ) {}

    /** @return array{rounds_resolved: int, matches_settled: int, challenges_expired: int} */
    public function processDue(int $limit = 100): array
    {
        return $this->matches->processDue($limit);
    }

    /** @return array{started: int, brackets_generated: int, matches_spawned: int, brackets_advanced: int, finished: int} */
    public function processTournaments(int $limit = 100): array
    {
        return $this->tournaments->processDue($limit);
    }

    /** @return int 1 when today's daily cup was created, 0 when it already existed */
    public function ensureDailyTournament(): int
    {
        return $this->tournaments->ensureDailyTournament() === null ? 0 : 1;
    }
}
