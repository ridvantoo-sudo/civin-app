<?php

namespace App\Features\GameAnalytics\Support;

use App\Features\GameAnalytics\Controllers\AnalyticsController;
use App\Features\Users\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

final class RouteRegistrar
{
    public static function register(): void
    {
        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api/v1/analytics')
            ->name('api.v1.analytics.')
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
    }

    public static function registerChannels(): void
    {
        Broadcast::channel(
            (string) config('game_analytics.realtime.channel', 'analytics.dashboard'),
            function (User $user): bool {
                return $user->status === User::STATUS_ACTIVE && ! $user->is_guest;
            },
        );
    }
}
