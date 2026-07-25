<?php

namespace App\Features\GameAnalytics\Services;

use App\Features\GameAnalytics\Jobs\AggregateDailyAnalyticsJob;
use App\Features\GameAnalytics\Jobs\AggregateHourlyAnalyticsJob;
use App\Features\GameAnalytics\Jobs\AggregateMonthlyAnalyticsJob;
use App\Features\GameAnalytics\Jobs\GenerateAnalyticsSnapshotJob;
use App\Features\GameAnalytics\Jobs\SendScheduledAnalyticsReportJob;
use App\Features\GameAnalytics\Models\ScheduledReport;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;

final class AnalyticsScheduler
{
    public function register(Schedule $schedule): void
    {
        $queue = (string) config('game_analytics.queue', 'analytics');
        $connection = (string) config('game_analytics.queue_connection', 'redis');

        $schedule->job(new AggregateHourlyAnalyticsJob, $queue, $connection)
            ->hourly()
            ->name('game-analytics:aggregate-hourly')
            ->onOneServer()
            ->withoutOverlapping();

        $schedule->job(new AggregateDailyAnalyticsJob, $queue, $connection)
            ->dailyAt('00:20')
            ->name('game-analytics:aggregate-daily')
            ->onOneServer()
            ->withoutOverlapping();

        $schedule->job(new AggregateMonthlyAnalyticsJob, $queue, $connection)
            ->monthlyOn(1, '00:40')
            ->name('game-analytics:aggregate-monthly')
            ->onOneServer()
            ->withoutOverlapping();

        $schedule->job(new GenerateAnalyticsSnapshotJob('executive'), $queue, $connection)
            ->everyFiveMinutes()
            ->name('game-analytics:snapshot-executive')
            ->onOneServer()
            ->withoutOverlapping();

        if (config('game_analytics.scheduled_reports.enabled', true)) {
            $schedule->job(new SendScheduledAnalyticsReportJob, $queue, $connection)
                ->everyFifteenMinutes()
                ->name('game-analytics:scheduled-reports')
                ->onOneServer()
                ->withoutOverlapping();
        }
    }

    public function markReportRan(ScheduledReport $report, string $status, ?string $error = null): void
    {
        $next = match ($report->frequency) {
            'hourly' => CarbonImmutable::now()->addHour(),
            'weekly' => CarbonImmutable::now()->addWeek(),
            'monthly' => CarbonImmutable::now()->addMonth(),
            default => CarbonImmutable::now()->addDay(),
        };

        $report->forceFill([
            'last_run_at' => now(),
            'last_status' => $status,
            'last_error' => $error,
            'next_run_at' => $next,
        ])->save();
    }
}
