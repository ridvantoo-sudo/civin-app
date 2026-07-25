<?php

namespace App\Features\Operations;

use App\Features\Operations\Console\StorageCheckCommand;
use Illuminate\Support\ServiceProvider;

final class OperationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../../config/operations.php',
            'operations',
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([StorageCheckCommand::class]);
        }
    }
}
