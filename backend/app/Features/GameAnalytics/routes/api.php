<?php

use App\Features\GameAnalytics\Controllers\AnalyticsController;
use Illuminate\Support\Facades\Route;

/*
| Optional module route file. Prefer App\Features\GameAnalytics\Support\RouteRegistrar
| which is invoked from GameAnalyticsServiceProvider.
*/

Route::middleware(['auth:sanctum'])
    ->prefix('analytics')
    ->name('analytics.')
    ->group(function (): void {
        Route::get('overview', [AnalyticsController::class, 'overview'])->name('overview');
        Route::get('revenue', [AnalyticsController::class, 'revenue'])->name('revenue');
        Route::get('games', [AnalyticsController::class, 'games'])->name('games');
        Route::get('players', [AnalyticsController::class, 'players'])->name('players');
        Route::get('retention', [AnalyticsController::class, 'retention'])->name('retention');
        Route::get('live', [AnalyticsController::class, 'live'])->name('live');
        Route::get('agencies', [AnalyticsController::class, 'agencies'])->name('agencies');
        Route::get('vip', [AnalyticsController::class, 'vip'])->name('vip');
        Route::get('export', [AnalyticsController::class, 'export'])->name('export');
    });
