<?php

namespace App\Filament\Widgets\Security;

use App\Features\Security\Models\SecurityScore;
use App\Filament\Widgets\Security\Concerns\SecurityWidget;
use Filament\Widgets\ChartWidget;

class RiskDistributionWidget extends ChartWidget
{
    use SecurityWidget;

    protected static ?int $sort = 3;

    protected static ?string $heading = 'Risk band distribution';

    protected static ?string $pollingInterval = '60s';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $bands = [
            SecurityScore::BAND_LOW => 'Low',
            SecurityScore::BAND_ELEVATED => 'Elevated',
            SecurityScore::BAND_HIGH => 'High',
            SecurityScore::BAND_CRITICAL => 'Critical',
        ];

        $counts = SecurityScore::query()
            ->selectRaw('band, count(*) as aggregate')
            ->groupBy('band')
            ->pluck('aggregate', 'band');

        return [
            'datasets' => [[
                'label' => 'Accounts',
                'data' => array_map(
                    static fn (string $band): int => (int) ($counts[$band] ?? 0),
                    array_keys($bands),
                ),
                'backgroundColor' => ['#22c55e', '#eab308', '#f97316', '#ef4444'],
            ]],
            'labels' => array_values($bands),
        ];
    }
}
