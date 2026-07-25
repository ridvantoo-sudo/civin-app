<?php

namespace App\Features\GameAnalytics\Console;

use App\Features\GameAnalytics\Services\ScheduledReportService;
use Illuminate\Console\Command;

final class ProcessScheduledReportsCommand extends Command
{
    protected $signature = 'game-analytics:process-reports';

    protected $description = 'Process due Game Analytics scheduled email reports';

    public function handle(ScheduledReportService $service): int
    {
        $processed = $service->processDue();
        $this->info("Processed {$processed} scheduled report(s).");

        return self::SUCCESS;
    }
}
