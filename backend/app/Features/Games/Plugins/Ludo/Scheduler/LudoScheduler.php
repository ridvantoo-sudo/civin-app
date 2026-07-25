<?php

namespace App\Features\Games\Plugins\Ludo\Scheduler;

use App\Features\Games\Plugins\Ludo\Services\LudoMatchService;
use App\Features\Games\Plugins\Ludo\Services\LudoTournamentService;

final readonly class LudoScheduler
{
    public function __construct(private LudoMatchService $matches, private LudoTournamentService $tournaments) {}

    public function processDue(int $limit = 100): int
    {
        return $this->matches->processDue($limit);
    }

    public function processTournaments(int $limit = 100): int
    {
        return $this->tournaments->processDue($limit);
    }
}
