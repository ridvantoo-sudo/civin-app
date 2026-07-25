<?php

namespace App\Features\Global;

use App\Features\Global\Console\ProcessTimezoneSchedulesCommand;
use App\Features\Global\Services\CdnService;
use App\Features\Global\Services\GeoComplianceService;
use App\Features\Global\Services\TimezoneService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * Global platform layer: geo compliance rules, user timezone preferences /
 * wall-clock schedules, and CDN optimization config.
 * Does not replace Countries, GeoDiscovery, or CreatorTools stream schedules.
 */
final class GlobalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/global.php', 'global');

        $this->app->singleton(GeoComplianceService::class);
        $this->app->singleton(TimezoneService::class);
        $this->app->singleton(CdnService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ProcessTimezoneSchedulesCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('global:process-schedules')->everyMinute()->withoutOverlapping();
        });
    }
}
