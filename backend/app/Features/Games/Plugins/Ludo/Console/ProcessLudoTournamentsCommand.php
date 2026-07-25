<?php

namespace App\Features\Games\Plugins\Ludo\Console;

use App\Features\Games\Plugins\Ludo\Scheduler\LudoScheduler;
use Illuminate\Console\Command;

final class ProcessLudoTournamentsCommand extends Command
{
    protected $signature = 'games:ludo:tournaments {--limit=100}';

    protected $description = 'Process due Ludo tournaments';

    public function handle(LudoScheduler $scheduler): int
    {
        $this->info('Ludo tournaments processed: '.$scheduler->processTournaments((int) $this->option('limit')));

        return self::SUCCESS;
    }
}
