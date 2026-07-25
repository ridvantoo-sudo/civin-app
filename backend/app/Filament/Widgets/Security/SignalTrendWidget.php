<?php

namespace App\Filament\Widgets\Security;

use App\Features\Security\Models\SecurityAlert;
use App\Features\Security\Models\SecurityEvent;
use App\Filament\Widgets\Security\Concerns\SecurityWidget;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class SignalTrendWidget extends ChartWidget
{
    use SecurityWidget;

    protected static ?int $sort = 4;

    protected static ?string $heading = 'Events vs. detections (14 days)';

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $start = CarbonImmutable::now()->subDays(13)->startOfDay();

        $labels = [];
        $events = [];
        $alerts = [];

        // Counted per day in PHP: the row volume over 14 days is small enough
        // and this stays portable across MySQL and SQLite.
        $eventBuckets = $this->bucket(
            SecurityEvent::query()->where('occurred_at', '>=', $start)->pluck('occurred_at')->all(),
        );
        $alertBuckets = $this->bucket(
            SecurityAlert::query()->where('created_at', '>=', $start)->pluck('created_at')->all(),
        );

        for ($offset = 0; $offset < 14; $offset++) {
            $day = $start->addDays($offset)->toDateString();
            $labels[] = $start->addDays($offset)->format('M j');
            $events[] = $eventBuckets[$day] ?? 0;
            $alerts[] = $alertBuckets[$day] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Events analysed',
                    'data' => $events,
                    'borderColor' => '#64748b',
                    'fill' => false,
                ],
                [
                    'label' => 'Detections',
                    'data' => $alerts,
                    'borderColor' => '#ef4444',
                    'fill' => false,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * @param  list<mixed>  $timestamps
     * @return array<string, int>
     */
    private function bucket(array $timestamps): array
    {
        $buckets = [];
        foreach ($timestamps as $timestamp) {
            if ($timestamp === null) {
                continue;
            }
            $day = CarbonImmutable::parse((string) $timestamp)->toDateString();
            $buckets[$day] = ($buckets[$day] ?? 0) + 1;
        }

        return $buckets;
    }
}
