<?php

namespace App\Filament\Widgets\Analytics;

use App\Features\GameAnalytics\Models\AnalyticsDaily;
use App\Filament\Widgets\Analytics\Concerns\AuthorizesAnalyticsWidget;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Surfaces anomaly signals from analytics daily aggregates only (no gameplay tables).
 */
class FraudAlertsWidget extends StatsOverviewWidget
{
    use AuthorizesAnalyticsWidget;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $from = CarbonImmutable::now()->subDays(30)->startOfDay();

        $base = AnalyticsDaily::query()->whereDate('stat_date', '>=', $from->toDateString());

        $highRtp = (clone $base)->where('rtp', '>', 1.05)->count();
        $negativeEdge = (clone $base)->where('house_edge', '<', 0)->count();
        $highAbandon = (clone $base)
            ->where('games_played', '>', 0)
            ->whereRaw('abandoned_games >= (games_played * 0.4)')
            ->count();
        $highReconnect = (clone $base)
            ->where('games_played', '>', 0)
            ->whereRaw('reconnect_count >= (games_played * 0.5)')
            ->count();

        $totalAlerts = $highRtp + $negativeEdge + $highAbandon + $highReconnect;

        return [
            Stat::make('Open signals', number_format($totalAlerts))
                ->description('Anomaly rows in last 30 days')
                ->icon('heroicon-o-shield-exclamation')
                ->color($totalAlerts > 0 ? 'danger' : 'success'),
            Stat::make('High RTP rows', number_format($highRtp))
                ->description('RTP > 1.05')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($highRtp > 0 ? 'warning' : 'gray'),
            Stat::make('Negative house edge', number_format($negativeEdge))
                ->description('House edge < 0')
                ->icon('heroicon-o-arrow-trending-down')
                ->color($negativeEdge > 0 ? 'danger' : 'gray'),
            Stat::make('High abandon / reconnect', number_format($highAbandon + $highReconnect))
                ->description('≥40% abandon or ≥50% reconnect')
                ->icon('heroicon-o-bolt')
                ->color(($highAbandon + $highReconnect) > 0 ? 'warning' : 'gray'),
        ];
    }
}
