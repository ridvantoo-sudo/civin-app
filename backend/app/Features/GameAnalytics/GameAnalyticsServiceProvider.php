<?php

namespace App\Features\GameAnalytics;

use App\Features\GameAnalytics\Console\AggregateAnalyticsCommand;
use App\Features\GameAnalytics\Console\ProcessScheduledReportsCommand;
use App\Features\GameAnalytics\Listeners\CollectDomainEventForAnalytics;
use App\Features\GameAnalytics\Models\AnalyticsEvent;
use App\Features\GameAnalytics\Policies\AnalyticsPolicy;
use App\Features\GameAnalytics\Repositories\Contracts\AnalyticsRepository;
use App\Features\GameAnalytics\Repositories\Eloquent\EloquentAnalyticsRepository;
use App\Features\GameAnalytics\Services\AnalyticsAggregator;
use App\Features\GameAnalytics\Services\AnalyticsCollector;
use App\Features\GameAnalytics\Services\AnalyticsQueryService;
use App\Features\GameAnalytics\Services\AnalyticsScheduler;
use App\Features\GameAnalytics\Services\ExportService;
use App\Features\GameAnalytics\Services\RealtimeAnalyticsService;
use App\Features\GameAnalytics\Services\ScheduledReportService;
use App\Features\GameAnalytics\Services\SnapshotService;
use App\Features\GameAnalytics\Support\EventNormalizer;
use App\Features\GameAnalytics\Support\RouteRegistrar;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class GameAnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/game_analytics.php', 'game_analytics');

        $this->app->singleton(EventNormalizer::class);
        $this->app->singleton(AnalyticsRepository::class, EloquentAnalyticsRepository::class);
        $this->app->singleton(AnalyticsCollector::class);
        $this->app->singleton(AnalyticsAggregator::class);
        $this->app->singleton(AnalyticsScheduler::class);
        $this->app->singleton(RealtimeAnalyticsService::class);
        $this->app->singleton(AnalyticsQueryService::class);
        $this->app->singleton(SnapshotService::class);
        $this->app->singleton(ExportService::class);
        $this->app->singleton(ScheduledReportService::class);
    }

    public function boot(): void
    {
        Gate::policy(AnalyticsEvent::class, AnalyticsPolicy::class);

        Event::listen('*', CollectDomainEventForAnalytics::class);

        RouteRegistrar::register();
        RouteRegistrar::registerChannels();

        if ($this->app->runningInConsole()) {
            $this->commands([
                AggregateAnalyticsCommand::class,
                ProcessScheduledReportsCommand::class,
            ]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $this->app->make(AnalyticsScheduler::class)->register($schedule);
        });
    }
}
