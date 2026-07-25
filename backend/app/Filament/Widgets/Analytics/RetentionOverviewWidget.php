<?php

namespace App\Filament\Widgets\Analytics;

use App\Features\GameAnalytics\Services\AnalyticsQueryService;
use App\Filament\Widgets\Analytics\Concerns\AuthorizesAnalyticsWidget;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RetentionOverviewWidget extends StatsOverviewWidget
{
    use AuthorizesAnalyticsWidget;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $query = app(AnalyticsQueryService::class);
        $payload = $query->retention($query->defaultFilters());
        $kpis = collect($payload['kpis'] ?? [])->keyBy('key');

        $value = static function (string $key) use ($kpis): string {
            $row = $kpis->get($key);
            if ($row === null) {
                return '0.00';
            }

            $formatted = number_format((float) ($row['value'] ?? 0), 2);
            $unit = $row['unit'] ?? null;

            return $unit ? "{$formatted}{$unit}" : $formatted;
        };

        return [
            Stat::make('Cohort size', $value('cohort_size'))
                ->description('Players in retention cohorts')
                ->icon('heroicon-o-user-group')
                ->color('primary'),
            Stat::make('D1 retention', $value('d1'))
                ->description('Day-1 average')
                ->icon('heroicon-o-calendar-days')
                ->color('success'),
            Stat::make('D7 retention', $value('d7'))
                ->description('Day-7 average')
                ->icon('heroicon-o-calendar')
                ->color('warning'),
            Stat::make('D30 retention', $value('d30'))
                ->description('Day-30 average')
                ->icon('heroicon-o-clock')
                ->color('danger'),
        ];
    }
}
