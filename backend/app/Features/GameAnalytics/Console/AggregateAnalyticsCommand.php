<?php

namespace App\Features\GameAnalytics\Console;

use App\Features\GameAnalytics\Jobs\AggregateDailyAnalyticsJob;
use App\Features\GameAnalytics\Jobs\AggregateHourlyAnalyticsJob;
use App\Features\GameAnalytics\Jobs\AggregateMonthlyAnalyticsJob;
use App\Features\GameAnalytics\Jobs\GenerateAnalyticsSnapshotJob;
use Illuminate\Console\Command;

final class AggregateAnalyticsCommand extends Command
{
    protected $signature = 'game-analytics:aggregate
        {period=daily : hourly|daily|monthly|all}
        {--date= : Optional ISO date/hour anchor}';

    protected $description = 'Queue Game Analytics aggregation jobs';

    public function handle(): int
    {
        $period = (string) $this->argument('period');
        $date = $this->option('date');
        $queue = (string) config('game_analytics.queue', 'analytics');
        $connection = (string) config('game_analytics.queue_connection', 'redis');

        $dispatch = function (object $job) use ($queue, $connection): void {
            dispatch($job)->onConnection($connection)->onQueue($queue);
        };

        match ($period) {
            'hourly' => $dispatch(new AggregateHourlyAnalyticsJob($date)),
            'daily' => $dispatch(new AggregateDailyAnalyticsJob($date)),
            'monthly' => $dispatch(new AggregateMonthlyAnalyticsJob($date)),
            'all' => tap(null, function () use ($dispatch, $date): void {
                $dispatch(new AggregateHourlyAnalyticsJob($date));
                $dispatch(new AggregateDailyAnalyticsJob($date));
                $dispatch(new AggregateMonthlyAnalyticsJob($date));
                $dispatch(new GenerateAnalyticsSnapshotJob('executive'));
            }),
            default => $this->error("Unknown period [{$period}]"),
        };

        $this->info("Queued game analytics aggregation for [{$period}].");

        return self::SUCCESS;
    }
}
