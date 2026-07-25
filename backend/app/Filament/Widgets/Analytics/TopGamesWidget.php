<?php

namespace App\Filament\Widgets\Analytics;

use App\Features\GameAnalytics\Services\AnalyticsQueryService;
use App\Filament\Widgets\Analytics\Concerns\AuthorizesAnalyticsWidget;
use Filament\Widgets\ChartWidget;

class TopGamesWidget extends ChartWidget
{
    use AuthorizesAnalyticsWidget;

    protected static ?string $heading = 'Top games';

    protected static ?string $description = 'By games played (analytics daily)';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected static ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $query = app(AnalyticsQueryService::class);
        $top = collect($query->overview($query->defaultFilters())['top_games'] ?? [])
            ->take(8);

        return [
            'datasets' => [
                [
                    'label' => 'Games played',
                    'data' => $top->map(fn ($row) => (float) ($row['value'] ?? $row['games_played'] ?? 0))->all(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.7)',
                    'borderColor' => 'rgb(59, 130, 246)',
                ],
            ],
            'labels' => $top->map(fn ($row) => (string) ($row['name'] ?? $row['game_slug'] ?? ''))->all(),
        ];
    }
}
