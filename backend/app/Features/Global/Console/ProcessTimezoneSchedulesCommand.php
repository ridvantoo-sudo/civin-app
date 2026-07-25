<?php

namespace App\Features\Global\Console;

use App\Features\Global\Services\TimezoneService;
use Illuminate\Console\Command;

final class ProcessTimezoneSchedulesCommand extends Command
{
    protected $signature = 'global:process-schedules {--limit=50}';

    protected $description = 'Mark due timezone-aware platform schedules as done.';

    public function handle(TimezoneService $timezones): int
    {
        $count = $timezones->processDue((int) $this->option('limit'));
        $this->info("Processed {$count} timezone schedule(s).");

        return self::SUCCESS;
    }
}
