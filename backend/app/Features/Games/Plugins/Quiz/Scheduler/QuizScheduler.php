<?php

namespace App\Features\Games\Plugins\Quiz\Scheduler;

use App\Features\Games\Plugins\Quiz\Services\QuizService;
use App\Features\Games\Plugins\Quiz\Services\QuizTournamentService;

final readonly class QuizScheduler
{
    public function __construct(
        private QuizService $service,
        private QuizTournamentService $tournaments,
    ) {}

    /** @return array{advanced: int, settled: int, tournaments_started: int, tournaments_finished: int} */
    public function processDue(int $limit = 100): array
    {
        return $this->service->processDueSessions($limit);
    }

    /** @return int total tournament transitions */
    public function processTournaments(int $limit = 100): int
    {
        $result = $this->tournaments->processDue($limit);

        return (int) $result['started'] + (int) $result['finished'];
    }
}
