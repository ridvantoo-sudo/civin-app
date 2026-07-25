<?php

namespace App\Filament\Widgets\Analytics;

use App\Features\GameAnalytics\Services\AnalyticsQueryService;
use App\Filament\Widgets\Analytics\Concerns\AuthorizesAnalyticsWidget;
use Filament\Widgets\ChartWidget;

class RevenueChartWidget extends ChartWidget
{
    use AuthorizesAnalyticsWidget;

    protected static ?string $heading = 'Revenue trend';

    protected static ?string $description = 'Daily revenue from analytics aggregates';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $query = app(AnalyticsQueryService::class);
        $overview = $query->overview($query->defaultFilters());
        $series = collect($overview['revenue_series'] ?? $overview['series'] ?? []);

        $byLabel = $series
            ->groupBy(fn ($row) => (string) ($row['label'] ?? $row['date'] ?? ''))
            ->map(fn ($rows) => (float) collect($rows)->sum(fn ($row) => (float) ($row['value'] ?? $row['revenue'] ?? 0)))
            ->filter(fn ($_, $label) => $label !== '')
            ->sortKeys();

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $byLabel->values()->all(),
                    'borderColor' => 'rgb(245, 158, 11)',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $byLabel->keys()->all(),
        ];
    }
}
