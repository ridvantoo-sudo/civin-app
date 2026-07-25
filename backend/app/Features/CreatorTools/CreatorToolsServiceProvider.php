<?php

namespace App\Features\CreatorTools;

use App\Features\CreatorTools\Console\ProcessCreatorToolsCommand;
use App\Features\CreatorTools\Listeners\FinalizeCreatorToolsOnLiveEnd;
use App\Features\CreatorTools\Services\RtmpIngestService;
use App\Features\CreatorTools\Services\StreamAnalyticsService;
use App\Features\CreatorTools\Services\StreamClipService;
use App\Features\CreatorTools\Services\StreamReplayService;
use App\Features\CreatorTools\Services\StreamSchedulerService;
use App\Features\LiveStreaming\Events\LiveEnded;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Creator tools: stream scheduler, clips/highlights, replay center, OBS/RTMP
 * ingest credentials, and host-facing stream analytics.
 *
 * Touch point with LiveStreaming is a single LiveEnded listener — no existing
 * live module files are modified.
 */
final class CreatorToolsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/creator_tools.php', 'creator_tools');

        $this->app->singleton(StreamSchedulerService::class);
        $this->app->singleton(StreamClipService::class);
        $this->app->singleton(StreamReplayService::class);
        $this->app->singleton(RtmpIngestService::class);
        $this->app->singleton(StreamAnalyticsService::class);
    }

    public function boot(): void
    {
        Event::listen(LiveEnded::class, FinalizeCreatorToolsOnLiveEnd::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ProcessCreatorToolsCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('creator-tools:process --schedules')->everyMinute()->withoutOverlapping();
            $schedule->command('creator-tools:process --snapshots')->everyMinute()->withoutOverlapping();
        });
    }
}
