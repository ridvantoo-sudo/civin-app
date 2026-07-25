<?php

namespace App\Features\Games\Plugins\Ludo\Console;

use App\Features\Games\Plugins\Ludo\Scheduler\LudoScheduler;
use Illuminate\Console\Command;

final class ProcessLudoMatchesCommand extends Command
{
    protected $signature = 'games:ludo:process {--limit=100}';

    protected $description = 'Process timed-out Ludo turns and forfeits';

    public function handle(LudoScheduler $scheduler): int
    {
        $this->info('Ludo matches processed: '.$scheduler->processDue((int) $this->option('limit')));

        return self::SUCCESS;
    }
}
