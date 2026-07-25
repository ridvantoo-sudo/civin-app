<?php

namespace App\Features\GameAnalytics\Services;

use App\Features\GameAnalytics\DTO\AnalyticsFilterData;
use App\Features\GameAnalytics\Repositories\Contracts\AnalyticsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class AnalyticsQueryService
{
    public function __construct(
        private readonly AnalyticsRepository $repository,
        private readonly RealtimeAnalyticsService $realtime,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(AnalyticsFilterData $filters): array
    {
        $daily = $this->repository->dailyStats($filters);
        $countries = $this->repository->countryStats($filters);
        $devices = $this->repository->deviceStats($filters);
        $realtime = $this->realtime->snapshot();

        return [
            'kpis' => $this->kpiList([
                ['key' => 'dau', 'label' => 'DAU', 'value' => (float) $daily->sum('dau')],
                ['key' => 'wau', 'label' => 'WAU', 'value' => (float) $daily->sum('unique_players')],
                ['key' => 'games_played', 'label' => 'Games Played', 'value' => (float) $daily->sum('games_played')],
                ['key' => 'revenue', 'label' => 'Revenue', 'value' => (float) $daily->sum('revenue'), 'unit' => 'coins'],
                ['key' => 'coins_wagered', 'label' => 'Coins Wagered', 'value' => (float) $daily->sum('coins_wagered'), 'unit' => 'coins'],
                ['key' => 'coins_won', 'label' => 'Coins Won', 'value' => (float) $daily->sum('coins_won'), 'unit' => 'coins'],
                ['key' => 'peak_ccu', 'label' => 'Peak CCU', 'value' => (float) $daily->max('peak_ccu')],
                ['key' => 'ccu', 'label' => 'CCU', 'value' => (float) ($realtime['ccu_estimate'] ?? 0)],
                ['key' => 'arpu', 'label' => 'ARPU', 'value' => $this->safeAvgFloat($daily, 'arpu')],
                ['key' => 'rtp', 'label' => 'RTP', 'value' => $this->safeAvgFloat($daily, 'rtp') * 100, 'unit' => '%'],
                ['key' => 'house_edge', 'label' => 'House Edge', 'value' => $this->safeAvgFloat($daily, 'house_edge') * 100, 'unit' => '%'],
            ]),
            'realtime' => $realtime,
            'revenue_series' => $this->seriesFromDaily($daily, 'revenue'),
            'series' => $this->seriesFromDaily($daily, 'games_played'),
            'top_games' => $this->namedTop($daily, 'game_slug', 'games_played'),
            'top_countries' => $this->namedTop($countries, 'country_code', 'revenue'),
            'top_devices' => $this->namedTop($devices, 'device_platform', 'dau'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function revenue(AnalyticsFilterData $filters): array
    {
        $rows = $this->repository->revenueStats($filters);
        $daily = $this->repository->dailyStats($filters);

        return [
            'kpis' => $this->kpiList([
                ['key' => 'gross', 'label' => 'Gross Revenue', 'value' => (float) $rows->sum('gross_revenue'), 'unit' => 'coins'],
                ['key' => 'net', 'label' => 'Net Revenue', 'value' => (float) $rows->sum('net_revenue'), 'unit' => 'coins'],
                ['key' => 'wagered', 'label' => 'Coins Wagered', 'value' => (float) $rows->sum('coins_wagered'), 'unit' => 'coins'],
                ['key' => 'won', 'label' => 'Coins Won', 'value' => (float) $rows->sum('coins_won'), 'unit' => 'coins'],
                ['key' => 'arpu', 'label' => 'ARPU', 'value' => $this->safeAvgFloat($rows, 'arpu')],
                ['key' => 'arppu', 'label' => 'ARPPU', 'value' => $this->safeAvgFloat($rows, 'arppu')],
            ]),
            'series' => $this->seriesFromRows($rows, 'stat_date', 'gross_revenue'),
            'by_game' => $this->namedTop($rows, 'game_slug', 'gross_revenue'),
            'by_country' => $this->namedTop($daily, 'country_code', 'revenue'),
            'items' => $rows->values(),
            'total_gross' => number_format((float) $rows->sum('gross_revenue'), 4, '.', ''),
            'total_net' => number_format((float) $rows->sum('net_revenue'), 4, '.', ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function games(AnalyticsFilterData $filters): array
    {
        $rows = $this->repository->dailyStats($filters);
        $leaderboard = $this->namedTop($rows, 'game_slug', 'revenue');

        return [
            'kpis' => $this->kpiList([
                ['key' => 'games_played', 'label' => 'Games Played', 'value' => (float) $rows->sum('games_played')],
                ['key' => 'unique_players', 'label' => 'Unique Players', 'value' => (float) $rows->sum('unique_players')],
                ['key' => 'revenue', 'label' => 'Revenue', 'value' => (float) $rows->sum('revenue'), 'unit' => 'coins'],
                ['key' => 'rtp', 'label' => 'Avg RTP', 'value' => $this->safeAvgFloat($rows, 'rtp') * 100, 'unit' => '%'],
                ['key' => 'jackpots', 'label' => 'Jackpots', 'value' => (float) $rows->sum('jackpots')],
                ['key' => 'abandoned', 'label' => 'Abandoned', 'value' => (float) $rows->sum('abandoned_games')],
            ]),
            'per_game' => $leaderboard,
            'top_games' => $leaderboard,
            'series' => $this->seriesFromDaily($rows, 'games_played'),
            'leaderboard' => $leaderboard,
            'items' => $rows->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function players(AnalyticsFilterData $filters): array
    {
        $players = $this->repository->playerStats($filters);
        $daily = $this->repository->dailyStats($filters);
        $devices = $this->repository->deviceStats($filters);

        return [
            'kpis' => $this->kpiList([
                ['key' => 'dau', 'label' => 'DAU', 'value' => (float) $daily->sum('dau')],
                ['key' => 'players', 'label' => 'Active Players', 'value' => (float) $players->count()],
                ['key' => 'games_played', 'label' => 'Games Played', 'value' => (float) $players->sum('games_played')],
                ['key' => 'win_rate', 'label' => 'Win Rate', 'value' => $this->playerWinRate($players) * 100, 'unit' => '%'],
            ]),
            'active_series' => $this->seriesFromDaily($daily, 'dau'),
            'series' => $this->seriesFromDaily($daily, 'dau'),
            'top_players' => $players->sortByDesc('games_played')->take(20)->map(fn ($row) => [
                'name' => (string) ($row->user_id ?? 'player'),
                'value' => (float) $row->games_played,
                'code' => (string) ($row->user_id ?? ''),
            ])->values()->all(),
            'by_device' => $this->namedTop($devices, 'device_platform', 'dau'),
            'top_devices' => $this->namedTop($devices, 'device_platform', 'dau'),
            'items' => $players->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function retention(AnalyticsFilterData $filters): array
    {
        $rows = $this->repository->retentionStats($filters);

        return [
            'kpis' => $this->kpiList([
                ['key' => 'd1', 'label' => 'Retention D1', 'value' => $this->safeAvgFloat($rows, 'retention_d1_rate') * 100, 'unit' => '%'],
                ['key' => 'd7', 'label' => 'Retention D7', 'value' => $this->safeAvgFloat($rows, 'retention_d7_rate') * 100, 'unit' => '%'],
                ['key' => 'd30', 'label' => 'Retention D30', 'value' => $this->safeAvgFloat($rows, 'retention_d30_rate') * 100, 'unit' => '%'],
                ['key' => 'cohort_size', 'label' => 'Cohort Size', 'value' => (float) $rows->sum('cohort_size')],
            ]),
            'cohort_series' => $rows->map(fn ($row) => [
                'label' => optional($row->cohort_date)?->toDateString() ?? (string) $row->cohort_date,
                'value' => (float) $row->retention_d1_rate * 100,
            ])->values()->all(),
            'series' => $rows->map(fn ($row) => [
                'label' => optional($row->cohort_date)?->toDateString() ?? (string) $row->cohort_date,
                'value' => (float) $row->retention_d7_rate * 100,
            ])->values()->all(),
            'by_country' => [],
            'items' => $rows->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function live(AnalyticsFilterData $filters): array
    {
        $rooms = $this->repository->roomStats(new AnalyticsFilterData(
            from: $filters->from,
            to: $filters->to,
            gameSlug: $filters->gameSlug,
            countryCode: $filters->countryCode,
            devicePlatform: $filters->devicePlatform,
            roomType: $filters->roomType ?? 'live',
            limit: $filters->limit,
        ));
        $hourly = $this->repository->hourlyStats($filters);
        $realtime = $this->realtime->snapshot();

        return [
            'kpis' => $this->kpiList([
                ['key' => 'ccu', 'label' => 'CCU', 'value' => (float) ($realtime['ccu_estimate'] ?? 0)],
                ['key' => 'peak_ccu', 'label' => 'Peak CCU', 'value' => (float) ($realtime['peak_ccu'] ?? $hourly->max('peak_ccu') ?? 0)],
                ['key' => 'rooms', 'label' => 'Tracked Rooms', 'value' => (float) $rooms->count()],
                ['key' => 'revenue', 'label' => 'Room Revenue', 'value' => (float) $rooms->sum('revenue'), 'unit' => 'coins'],
            ]),
            'realtime' => $realtime,
            'series' => $hourly->sortBy('hour_start')->map(fn ($row) => [
                'label' => optional($row->hour_start)?->format('H:i') ?? '',
                'value' => (float) ($row->peak_ccu ?: $row->unique_players),
            ])->values()->all(),
            'ccu_series' => $hourly->sortBy('hour_start')->map(fn ($row) => [
                'label' => optional($row->hour_start)?->format('H:i') ?? '',
                'value' => (float) ($row->peak_ccu ?: $row->unique_players),
            ])->values()->all(),
            'top_rooms' => $rooms->sortByDesc('revenue')->take(20)->map(fn ($row) => [
                'name' => (string) ($row->room_id ?? $row->room_type ?? 'room'),
                'value' => (float) $row->revenue,
                'code' => (string) ($row->room_id ?? ''),
            ])->values()->all(),
            'rooms' => $rooms->values(),
            'hourly' => $hourly->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function agencies(AnalyticsFilterData $filters): array
    {
        $rooms = $this->repository->roomStats($filters);
        $topHosts = $rooms->sortByDesc('revenue')->take(20)->values();

        return [
            'kpis' => $this->kpiList([
                ['key' => 'hosts', 'label' => 'Active Hosts', 'value' => (float) $topHosts->count()],
                ['key' => 'revenue', 'label' => 'Host Revenue', 'value' => (float) $rooms->sum('revenue'), 'unit' => 'coins'],
            ]),
            'top_hosts' => $topHosts,
            'items' => $rooms->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function vip(AnalyticsFilterData $filters): array
    {
        $players = $this->repository->playerStats($filters);
        $top = $players->sortByDesc('revenue_contributed')->take(20)->values();

        return [
            'kpis' => $this->kpiList([
                ['key' => 'vip_candidates', 'label' => 'Top Spenders', 'value' => (float) $top->count()],
                ['key' => 'revenue', 'label' => 'Contributed', 'value' => (float) $players->sum('revenue_contributed'), 'unit' => 'coins'],
            ]),
            'top_vip_candidates' => $top,
            'items' => $players->values(),
        ];
    }

    /**
     * @param  list<array{key: string, label: string, value: float, unit?: string, delta?: float|null}>  $items
     * @return list<array<string, mixed>>
     */
    private function kpiList(array $items): array
    {
        return array_map(static function (array $item): array {
            return [
                'key' => $item['key'],
                'label' => $item['label'],
                'value' => round((float) $item['value'], 4),
                'unit' => $item['unit'] ?? null,
                'delta' => $item['delta'] ?? null,
            ];
        }, $items);
    }

    /**
     * @param  Collection<int, mixed>  $rows
     * @return list<array{label: string, value: float}>
     */
    private function seriesFromDaily(Collection $rows, string $metric): array
    {
        return $rows
            ->sortBy('stat_date')
            ->map(fn ($row) => [
                'label' => optional($row->stat_date)?->toDateString() ?? (string) data_get($row, 'stat_date'),
                'value' => (float) data_get($row, $metric, 0),
                'date' => optional($row->stat_date)?->toDateString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, mixed>  $rows
     * @return list<array{label: string, value: float}>
     */
    private function seriesFromRows(Collection $rows, string $labelField, string $metric): array
    {
        return $rows
            ->sortBy($labelField)
            ->map(function ($row) use ($labelField, $metric): array {
                $label = data_get($row, $labelField);
                if ($label instanceof \DateTimeInterface) {
                    $label = CarbonImmutable::instance($label)->toDateString();
                }

                return [
                    'label' => (string) $label,
                    'value' => (float) data_get($row, $metric, 0),
                    'date' => (string) $label,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, mixed>  $rows
     * @return list<array{name: string, value: float, code: string}>
     */
    private function namedTop(Collection $rows, string $key, string $metric): array
    {
        return $rows
            ->filter(fn ($row) => filled(data_get($row, $key)))
            ->groupBy($key)
            ->map(fn (Collection $group, $groupKey) => [
                'name' => (string) $groupKey,
                'value' => (float) $group->sum($metric),
                'code' => (string) $groupKey,
                $key => $groupKey,
                $metric => $group->sum($metric),
            ])
            ->sortByDesc('value')
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, mixed>  $rows
     */
    private function safeAvgFloat(Collection $rows, string $field): float
    {
        if ($rows->isEmpty()) {
            return 0.0;
        }

        return (float) $rows->avg($field);
    }

    /**
     * @param  Collection<int, mixed>  $players
     */
    private function playerWinRate(Collection $players): float
    {
        $wins = (float) $players->sum('wins');
        $losses = (float) $players->sum('losses');
        $total = $wins + $losses;

        return $total > 0 ? $wins / $total : 0.0;
    }

    public function defaultFilters(?string $from = null, ?string $to = null): AnalyticsFilterData
    {
        return new AnalyticsFilterData(
            from: $from ? CarbonImmutable::parse($from)->startOfDay() : CarbonImmutable::now()->subDays(30)->startOfDay(),
            to: $to ? CarbonImmutable::parse($to)->endOfDay() : CarbonImmutable::now()->endOfDay(),
        );
    }
}
