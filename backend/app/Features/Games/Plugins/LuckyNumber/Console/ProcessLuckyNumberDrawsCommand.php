<?php

namespace App\Features\Games\Plugins\LuckyNumber\Console;

use App\Features\Games\Plugins\LuckyNumber\Scheduler\LuckyNumberScheduler;
use Illuminate\Console\Command;

final class ProcessLuckyNumberDrawsCommand extends Command
{
    protected $signature = 'games:lucky-number:process-draws {--limit=50}';

    protected $description = 'Process due Lucky Number scheduled draws';

    public function handle(LuckyNumberScheduler $scheduler): int
    {
        $processed = $scheduler->processDueDraws((int) $this->option('limit'));
        $this->info("Processed {$processed} due Lucky Number draw(s).");

        return self::SUCCESS;
    }
}
