<?php

namespace App\Features\GameAnalytics\Services;

use App\Features\GameAnalytics\Events\AnalyticsUpdated;
use App\Features\GameAnalytics\Events\DashboardUpdated;
use App\Features\GameAnalytics\Events\LeaderboardUpdated;
use App\Features\GameAnalytics\Events\PlayerCountUpdated;
use App\Features\GameAnalytics\Events\RevenueUpdated;
use App\Features\GameAnalytics\Models\AnalyticsEvent;
use App\Features\GameAnalytics\Repositories\Contracts\AnalyticsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class AnalyticsAggregator
{
    public function __construct(
        private readonly AnalyticsRepository $repository,
        private readonly RealtimeAnalyticsService $realtime,
    ) {}

    public function aggregateHour(?CarbonImmutable $hour = null): int
    {
        $hourStart = ($hour ?? CarbonImmutable::now()->subHour())->startOfHour();
        $hourEnd = $hourStart->endOfHour();
        $rows = $this->repository->aggregateRawForHour($hourStart, $hourEnd);
        $count = 0;

        foreach ($rows as $row) {
            $wagered = (float) $row->coins_wagered;
            $won = (float) $row->coins_won;
            $revenue = (float) $row->revenue;
            $rtp = $wagered > 0 ? round($won / $wagered, 4) : 0.0;
            $houseEdge = $wagered > 0 ? round(($wagered - $won) / $wagered, 4) : 0.0;

            $this->repository->upsertHourly(
                [
                    'hour_start' => $hourStart,
                    'game_slug' => $row->game_slug,
                    'country_code' => $row->country_code,
                    'device_platform' => $row->device_platform,
                ],
                [
                    'games_played' => (int) $row->games_played,
                    'unique_players' => (int) $row->unique_players,
                    'matches_started' => (int) $row->matches_started,
                    'matches_finished' => (int) $row->matches_finished,
                    'reconnect_count' => (int) $row->reconnect_count,
                    'abandoned_games' => (int) $row->abandoned_games,
                    'coins_wagered' => number_format($wagered, 4, '.', ''),
                    'coins_won' => number_format($won, 4, '.', ''),
                    'coins_burned' => number_format((float) $row->coins_burned, 4, '.', ''),
                    'diamonds_consumed' => number_format((float) $row->diamonds_consumed, 4, '.', ''),
                    'revenue' => number_format($revenue, 4, '.', ''),
                    'profit' => number_format($wagered - $won, 4, '.', ''),
                    'avg_bet' => (int) $row->games_played > 0
                        ? number_format($wagered / (int) $row->games_played, 4, '.', '')
                        : '0.0000',
                    'avg_reward' => (int) $row->games_played > 0
                        ? number_format($won / (int) $row->games_played, 4, '.', '')
                        : '0.0000',
                    'rtp' => number_format($rtp, 4, '.', ''),
                    'house_edge' => number_format($houseEdge, 4, '.', ''),
                    'jackpots' => (int) $row->jackpots,
                    'bonus_wins' => (int) $row->bonus_wins,
                    'free_plays' => (int) $row->free_plays,
                    'peak_ccu' => (int) $row->unique_players,
                ],
            );
            $count++;
        }

        event(new AnalyticsUpdated(['period' => 'hourly', 'hour' => $hourStart->toIso8601String(), 'rows' => $count]));

        return $count;
    }

    public function aggregateDay(?CarbonImmutable $day = null): int
    {
        $dayStart = ($day ?? CarbonImmutable::yesterday())->startOfDay();
        $dayEnd = $dayStart->endOfDay();
        $rows = $this->repository->aggregateRawForDay($dayStart, $dayEnd);
        $count = 0;
        $totalRevenue = 0.0;
        $totalPlayers = 0;

        foreach ($rows as $row) {
            $wagered = (float) $row->coins_wagered;
            $won = (float) $row->coins_won;
            $revenue = (float) $row->revenue;
            $wins = (int) $row->wins;
            $losses = (int) $row->losses;
            $decided = max($wins + $losses, 1);
            $rtp = $wagered > 0 ? round($won / $wagered, 4) : 0.0;
            $houseEdge = $wagered > 0 ? round(($wagered - $won) / $wagered, 4) : 0.0;
            $unique = (int) $row->unique_players;
            $returning = $this->countReturningPlayers($dayStart, $row->game_slug, $row->country_code, $row->device_platform);

            $this->repository->upsertDaily(
                [
                    'stat_date' => $dayStart->toDateString(),
                    'game_slug' => $row->game_slug,
                    'country_code' => $row->country_code,
                    'device_platform' => $row->device_platform,
                ],
                [
                    'games_played' => (int) $row->games_played,
                    'unique_players' => $unique,
                    'returning_players' => $returning,
                    'matches_started' => (int) $row->matches_started,
                    'matches_finished' => (int) $row->matches_finished,
                    'reconnect_count' => (int) $row->reconnect_count,
                    'abandoned_games' => (int) $row->abandoned_games,
                    'wins' => $wins,
                    'losses' => $losses,
                    'coins_wagered' => number_format($wagered, 4, '.', ''),
                    'coins_won' => number_format($won, 4, '.', ''),
                    'coins_burned' => number_format((float) $row->coins_burned, 4, '.', ''),
                    'diamonds_consumed' => number_format((float) $row->diamonds_consumed, 4, '.', ''),
                    'revenue' => number_format($revenue, 4, '.', ''),
                    'profit' => number_format($wagered - $won, 4, '.', ''),
                    'avg_bet' => (int) $row->games_played > 0
                        ? number_format($wagered / (int) $row->games_played, 4, '.', '')
                        : '0.0000',
                    'avg_reward' => (int) $row->games_played > 0
                        ? number_format($won / (int) $row->games_played, 4, '.', '')
                        : '0.0000',
                    'rtp' => number_format($rtp, 4, '.', ''),
                    'house_edge' => number_format($houseEdge, 4, '.', ''),
                    'win_rate' => number_format($wins / $decided, 4, '.', ''),
                    'loss_rate' => number_format($losses / $decided, 4, '.', ''),
                    'jackpots' => (int) $row->jackpots,
                    'bonus_wins' => (int) $row->bonus_wins,
                    'free_plays' => (int) $row->free_plays,
                    'dau' => $unique,
                    'peak_ccu' => $unique,
                    'arpu' => $unique > 0 ? number_format($revenue / $unique, 4, '.', '') : '0.0000',
                    'arppu' => $unique > 0 && $revenue > 0
                        ? number_format($revenue / max($unique, 1), 4, '.', '')
                        : '0.0000',
                    'conversion_rate' => $unique > 0
                        ? number_format(min(1, $revenue > 0 ? 1 : 0), 4, '.', '')
                        : '0.0000',
                ],
            );

            $this->repository->upsertRevenueStatistic(
                [
                    'stat_date' => $dayStart->toDateString(),
                    'game_slug' => $row->game_slug,
                    'source' => 'games',
                ],
                [
                    'gross_revenue' => number_format($revenue, 4, '.', ''),
                    'net_revenue' => number_format($wagered - $won, 4, '.', ''),
                    'coins_wagered' => number_format($wagered, 4, '.', ''),
                    'coins_won' => number_format($won, 4, '.', ''),
                    'coins_burned' => number_format((float) $row->coins_burned, 4, '.', ''),
                    'diamonds_consumed' => number_format((float) $row->diamonds_consumed, 4, '.', ''),
                    'paying_users' => $unique,
                    'arpu' => $unique > 0 ? number_format($revenue / $unique, 4, '.', '') : '0.0000',
                    'arppu' => $unique > 0 ? number_format($revenue / $unique, 4, '.', '') : '0.0000',
                ],
            );

            if ($row->country_code) {
                $this->repository->upsertCountryStatistic(
                    [
                        'stat_date' => $dayStart->toDateString(),
                        'country_code' => $row->country_code,
                    ],
                    [
                        'dau' => $unique,
                        'games_played' => (int) $row->games_played,
                        'revenue' => number_format($revenue, 4, '.', ''),
                        'coins_wagered' => number_format($wagered, 4, '.', ''),
                    ],
                );
            }

            if ($row->device_platform) {
                $this->repository->upsertDeviceStatistic(
                    [
                        'stat_date' => $dayStart->toDateString(),
                        'device_platform' => $row->device_platform,
                    ],
                    [
                        'dau' => $unique,
                        'games_played' => (int) $row->games_played,
                        'revenue' => number_format($revenue, 4, '.', ''),
                        'coins_wagered' => number_format($wagered, 4, '.', ''),
                    ],
                );
            }

            $this->aggregatePlayerRows($dayStart, $dayEnd, $row->game_slug);
            $this->aggregateRoomRows($dayStart, $dayEnd);

            $totalRevenue += $revenue;
            $totalPlayers += $unique;
            $count++;
        }

        $this->aggregateRetention($dayStart);

        event(new RevenueUpdated([
            'date' => $dayStart->toDateString(),
            'revenue' => number_format($totalRevenue, 4, '.', ''),
        ]));
        event(new PlayerCountUpdated([
            'date' => $dayStart->toDateString(),
            'dau' => $totalPlayers,
        ]));
        event(new LeaderboardUpdated([
            'date' => $dayStart->toDateString(),
            'scope' => 'games',
        ]));
        event(new DashboardUpdated([
            'date' => $dayStart->toDateString(),
            'rows' => $count,
        ]));
        event(new AnalyticsUpdated([
            'period' => 'daily',
            'date' => $dayStart->toDateString(),
            'rows' => $count,
        ]));

        $this->realtime->refreshDashboardCache($dayStart);

        return $count;
    }

    public function aggregateMonth(?CarbonImmutable $month = null): int
    {
        $anchor = ($month ?? CarbonImmutable::now()->subMonth())->startOfMonth();
        $periodKey = $anchor->format('Y-m');
        $from = $anchor;
        $to = $anchor->endOfMonth();
        $rows = $this->repository->aggregateRawForDay($from, $to);
        $count = 0;

        foreach ($rows as $row) {
            $wagered = (float) $row->coins_wagered;
            $won = (float) $row->coins_won;
            $revenue = (float) $row->revenue;
            $unique = (int) $row->unique_players;

            $this->repository->upsertMonthly(
                [
                    'period_key' => $periodKey,
                    'game_slug' => $row->game_slug,
                    'country_code' => $row->country_code,
                    'device_platform' => $row->device_platform,
                ],
                [
                    'games_played' => (int) $row->games_played,
                    'unique_players' => $unique,
                    'returning_players' => 0,
                    'matches_started' => (int) $row->matches_started,
                    'matches_finished' => (int) $row->matches_finished,
                    'coins_wagered' => number_format($wagered, 4, '.', ''),
                    'coins_won' => number_format($won, 4, '.', ''),
                    'coins_burned' => number_format((float) $row->coins_burned, 4, '.', ''),
                    'diamonds_consumed' => number_format((float) $row->diamonds_consumed, 4, '.', ''),
                    'revenue' => number_format($revenue, 4, '.', ''),
                    'profit' => number_format($wagered - $won, 4, '.', ''),
                    'rtp' => $wagered > 0 ? number_format($won / $wagered, 4, '.', '') : '0.0000',
                    'house_edge' => $wagered > 0 ? number_format(($wagered - $won) / $wagered, 4, '.', '') : '0.0000',
                    'mau' => $unique,
                    'wau' => $unique,
                    'arpu' => $unique > 0 ? number_format($revenue / $unique, 4, '.', '') : '0.0000',
                    'arppu' => $unique > 0 ? number_format($revenue / $unique, 4, '.', '') : '0.0000',
                ],
            );
            $count++;
        }

        event(new AnalyticsUpdated(['period' => 'monthly', 'period_key' => $periodKey, 'rows' => $count]));

        return $count;
    }

    private function countReturningPlayers(
        CarbonImmutable $day,
        ?string $gameSlug,
        ?string $countryCode,
        ?string $devicePlatform,
    ): int {
        $todayUsers = AnalyticsEvent::query()
            ->whereDate(DB::raw('COALESCE(occurred_at, created_at)'), $day->toDateString())
            ->when($gameSlug, fn ($q) => $q->where('game_slug', $gameSlug))
            ->when($countryCode, fn ($q) => $q->where('country_code', $countryCode))
            ->when($devicePlatform, fn ($q) => $q->where('device_platform', $devicePlatform))
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        if ($todayUsers->isEmpty()) {
            return 0;
        }

        return AnalyticsEvent::query()
            ->whereIn('user_id', $todayUsers)
            ->whereDate(DB::raw('COALESCE(occurred_at, created_at)'), '<', $day->toDateString())
            ->when($gameSlug, fn ($q) => $q->where('game_slug', $gameSlug))
            ->distinct('user_id')
            ->count('user_id');
    }

    private function aggregatePlayerRows(CarbonImmutable $from, CarbonImmutable $to, ?string $gameSlug): void
    {
        $rows = AnalyticsEvent::query()
            ->select([
                'user_id',
                'game_slug',
                DB::raw('COUNT(*) as games_played'),
                DB::raw("SUM(CASE WHEN event LIKE '%win%' THEN 1 ELSE 0 END) as wins"),
                DB::raw("SUM(CASE WHEN event LIKE '%loss%' OR event LIKE '%lost%' THEN 1 ELSE 0 END) as losses"),
                DB::raw("SUM(CASE WHEN event LIKE '%reconnect%' THEN 1 ELSE 0 END) as reconnect_count"),
                DB::raw('COALESCE(SUM(coins_wagered), 0) as coins_wagered'),
                DB::raw('COALESCE(SUM(coins_won), 0) as coins_won'),
                DB::raw('COALESCE(SUM(revenue), 0) as revenue_contributed'),
                DB::raw('MIN(COALESCE(occurred_at, created_at)) as first_played_at'),
                DB::raw('MAX(COALESCE(occurred_at, created_at)) as last_played_at'),
            ])
            ->whereNotNull('user_id')
            ->whereBetween(DB::raw('COALESCE(occurred_at, created_at)'), [$from, $to])
            ->when($gameSlug, fn ($q) => $q->where('game_slug', $gameSlug))
            ->groupBy('user_id', 'game_slug')
            ->limit(5000)
            ->get();

        foreach ($rows as $row) {
            $this->repository->upsertPlayerStatistic(
                [
                    'user_id' => $row->user_id,
                    'game_slug' => $row->game_slug,
                    'stat_date' => $from->toDateString(),
                ],
                [
                    'games_played' => (int) $row->games_played,
                    'wins' => (int) $row->wins,
                    'losses' => (int) $row->losses,
                    'reconnect_count' => (int) $row->reconnect_count,
                    'coins_wagered' => number_format((float) $row->coins_wagered, 4, '.', ''),
                    'coins_won' => number_format((float) $row->coins_won, 4, '.', ''),
                    'revenue_contributed' => number_format((float) $row->revenue_contributed, 4, '.', ''),
                    'first_played_at' => $row->first_played_at,
                    'last_played_at' => $row->last_played_at,
                ],
            );
        }
    }

    private function aggregateRoomRows(CarbonImmutable $from, CarbonImmutable $to): void
    {
        $rows = AnalyticsEvent::query()
            ->select([
                'room_type',
                'room_id',
                DB::raw('COUNT(*) as sessions'),
                DB::raw('COUNT(DISTINCT user_id) as unique_players'),
                DB::raw('COALESCE(SUM(coins_wagered), 0) as coins_wagered'),
                DB::raw('COALESCE(SUM(revenue), 0) as revenue'),
            ])
            ->whereNotNull('room_id')
            ->whereBetween(DB::raw('COALESCE(occurred_at, created_at)'), [$from, $to])
            ->groupBy('room_type', 'room_id')
            ->limit(2000)
            ->get();

        foreach ($rows as $row) {
            $this->repository->upsertRoomStatistic(
                [
                    'room_type' => $row->room_type ?? 'unknown',
                    'room_id' => $row->room_id,
                    'stat_date' => $from->toDateString(),
                ],
                [
                    'sessions' => (int) $row->sessions,
                    'unique_players' => (int) $row->unique_players,
                    'peak_ccu' => (int) $row->unique_players,
                    'coins_wagered' => number_format((float) $row->coins_wagered, 4, '.', ''),
                    'revenue' => number_format((float) $row->revenue, 4, '.', ''),
                ],
            );
        }
    }

    private function aggregateRetention(CarbonImmutable $cohortDate): void
    {
        $cohortUsers = AnalyticsEvent::query()
            ->whereDate(DB::raw('COALESCE(occurred_at, created_at)'), $cohortDate->toDateString())
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        $size = $cohortUsers->count();
        if ($size === 0) {
            return;
        }

        $retained = [];
        foreach ([1, 7, 30] as $day) {
            $target = $cohortDate->addDays($day)->toDateString();
            $retained[$day] = AnalyticsEvent::query()
                ->whereIn('user_id', $cohortUsers)
                ->whereDate(DB::raw('COALESCE(occurred_at, created_at)'), $target)
                ->distinct('user_id')
                ->count('user_id');
        }

        $this->repository->upsertRetentionStatistic(
            [
                'cohort_date' => $cohortDate->toDateString(),
                'cohort_type' => 'daily',
                'cohort_key' => 'all',
            ],
            [
                'cohort_size' => $size,
                'retained_d1' => $retained[1],
                'retained_d7' => $retained[7],
                'retained_d30' => $retained[30],
                'retention_d1_rate' => number_format($retained[1] / $size, 4, '.', ''),
                'retention_d7_rate' => number_format($retained[7] / $size, 4, '.', ''),
                'retention_d30_rate' => number_format($retained[30] / $size, 4, '.', ''),
            ],
        );
    }
}
