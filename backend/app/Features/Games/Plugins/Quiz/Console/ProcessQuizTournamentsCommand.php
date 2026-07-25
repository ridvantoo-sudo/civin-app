<?php

namespace App\Features\Games\Plugins\Quiz\Console;

use App\Features\Games\Plugins\Quiz\Scheduler\QuizScheduler;
use Illuminate\Console\Command;

final class ProcessQuizTournamentsCommand extends Command
{
    protected $signature = 'games:quiz:process-tournaments';

    protected $description = 'Process due and expired Quiz tournaments';

    public function handle(QuizScheduler $scheduler): int
    {
        $processed = $scheduler->processTournaments();
        $this->info("Processed {$processed} tournament transition(s).");

        return self::SUCCESS;
    }
}
