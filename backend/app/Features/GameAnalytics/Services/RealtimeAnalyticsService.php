<?php

namespace App\Features\GameAnalytics\Services;

use App\Features\GameAnalytics\DTO\AnalyticsFilterData;
use App\Features\GameAnalytics\Repositories\Contracts\AnalyticsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

final class RealtimeAnalyticsService
{
    public function __construct(
        private readonly AnalyticsRepository $repository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $cache = $this->cache();
        $ttl = (int) config('game_analytics.cache_ttl_seconds', 60);
        $key = 'ga:realtime:snapshot';

        return $cache->remember($key, $ttl, function (): array {
            $today = CarbonImmutable::now()->toDateString();
            $filters = new AnalyticsFilterData(
                from: CarbonImmutable::now()->startOfDay(),
                to: CarbonImmutable::now()->endOfDay(),
                limit: 100,
            );
            $daily = $this->repository->dailyStats($filters);
            $hourly = $this->repository->hourlyStats($filters);

            return [
                'date' => $today,
                'ccu_estimate' => (int) ($hourly->first()?->unique_players ?? 0),
                'peak_ccu' => (int) $hourly->max('peak_ccu'),
                'dau' => (int) $daily->sum('dau'),
                'revenue_today' => number_format((float) $daily->sum('revenue'), 4, '.', ''),
                'games_played_today' => (int) $daily->sum('games_played'),
                'updated_at' => CarbonImmutable::now()->toIso8601String(),
            ];
        });
    }

    public function refreshDashboardCache(?CarbonImmutable $day = null): void
    {
        $this->cache()->forget('ga:realtime:snapshot');
        $this->snapshot();
    }

    private function cache(): CacheRepository
    {
        return Cache::store((string) config('game_analytics.cache_store', config('cache.default')));
    }
}
