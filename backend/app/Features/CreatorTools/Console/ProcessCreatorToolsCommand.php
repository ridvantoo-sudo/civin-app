<?php

namespace App\Features\CreatorTools\Console;

use App\Features\CreatorTools\Services\StreamAnalyticsService;
use App\Features\CreatorTools\Services\StreamSchedulerService;
use Illuminate\Console\Command;

final class ProcessCreatorToolsCommand extends Command
{
    protected $signature = 'creator-tools:process {--snapshots : Capture live analytics snapshots} {--schedules : Process due schedules and reminders}';

    protected $description = 'Process creator tools: schedule reminders/due rooms and live analytics snapshots';

    public function handle(StreamSchedulerService $scheduler, StreamAnalyticsService $analytics): int
    {
        $doAll = ! $this->option('snapshots') && ! $this->option('schedules');

        if ($doAll || $this->option('schedules')) {
            $reminded = $scheduler->markReminders();
            $started = $scheduler->processDue();
            $this->info("Schedules: reminded={$reminded} started={$started}");
        }

        if ($doAll || $this->option('snapshots')) {
            $snaps = $analytics->snapshotLiveRooms();
            $this->info("Analytics snapshots: {$snaps}");
        }

        return self::SUCCESS;
    }
}
