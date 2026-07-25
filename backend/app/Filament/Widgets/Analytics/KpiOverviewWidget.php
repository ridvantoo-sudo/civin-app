<?php

namespace App\Filament\Widgets\Analytics;

use App\Features\GameAnalytics\Services\AnalyticsQueryService;
use App\Filament\Widgets\Analytics\Concerns\AuthorizesAnalyticsWidget;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class KpiOverviewWidget extends StatsOverviewWidget
{
    use AuthorizesAnalyticsWidget;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $query = app(AnalyticsQueryService::class);
        $kpis = collect($query->overview($query->defaultFilters())['kpis'] ?? [])
            ->keyBy('key');

        $value = static function (string $key, string $fallback = '0') use ($kpis): string {
            $row = $kpis->get($key);
            if ($row === null) {
                return $fallback;
            }

            $formatted = number_format((float) ($row['value'] ?? 0), 2);
            $unit = $row['unit'] ?? null;

            return $unit ? "{$formatted} {$unit}" : $formatted;
        };

        return [
            Stat::make('DAU', $value('dau', '0.00'))
                ->description('Daily active users (30d window)')
                ->icon('heroicon-o-users')
                ->color('primary'),
            Stat::make('Games Played', $value('games_played', '0.00'))
                ->description('Across all game slugs')
                ->icon('heroicon-o-puzzle-piece')
                ->color('success'),
            Stat::make('Revenue', $value('revenue', '0.00'))
                ->description('Aggregated from daily stats')
                ->icon('heroicon-o-banknotes')
                ->color('warning'),
            Stat::make('Peak CCU', $value('peak_ccu', '0.00'))
                ->description('Max concurrent from snapshots')
                ->icon('heroicon-o-signal')
                ->color('info'),
            Stat::make('ARPU', $value('arpu', '0.00'))
                ->description('Average revenue per user')
                ->icon('heroicon-o-chart-bar')
                ->color('gray'),
            Stat::make('RTP', $value('rtp', '0.00'))
                ->description('Avg return to player')
                ->icon('heroicon-o-scale')
                ->color('danger'),
        ];
    }
}
