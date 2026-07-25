<?php

namespace App\Filament\Widgets\Analytics;

use App\Features\GameAnalytics\Services\RealtimeAnalyticsService;
use App\Filament\Widgets\Analytics\Concerns\AuthorizesAnalyticsWidget;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CcuOverviewWidget extends StatsOverviewWidget
{
    use AuthorizesAnalyticsWidget;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $snapshot = app(RealtimeAnalyticsService::class)->snapshot();

        return [
            Stat::make('CCU estimate', number_format((int) ($snapshot['ccu_estimate'] ?? 0)))
                ->description('From hourly unique players')
                ->icon('heroicon-o-signal')
                ->color('success'),
            Stat::make('Peak CCU today', number_format((int) ($snapshot['peak_ccu'] ?? 0)))
                ->description('Peak concurrent today')
                ->icon('heroicon-o-arrow-trending-up')
                ->color('warning'),
            Stat::make('DAU today', number_format((int) ($snapshot['dau'] ?? 0)))
                ->description('Daily active users')
                ->icon('heroicon-o-users')
                ->color('primary'),
            Stat::make('Revenue today', (string) ($snapshot['revenue_today'] ?? '0.0000'))
                ->description('Games played: '.number_format((int) ($snapshot['games_played_today'] ?? 0)))
                ->icon('heroicon-o-banknotes')
                ->color('info'),
        ];
    }
}
