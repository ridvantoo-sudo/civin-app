<?php

namespace App\Features\GameAnalytics\Repositories\Eloquent;

use App\Features\GameAnalytics\DTO\AnalyticsFilterData;
use App\Features\GameAnalytics\DTO\NormalizedAnalyticsEvent;
use App\Features\GameAnalytics\Models\AnalyticsDaily;
use App\Features\GameAnalytics\Models\AnalyticsEvent;
use App\Features\GameAnalytics\Models\AnalyticsHourly;
use App\Features\GameAnalytics\Models\AnalyticsMonthly;
use App\Features\GameAnalytics\Models\AnalyticsSnapshot;
use App\Features\GameAnalytics\Models\CountryStatistic;
use App\Features\GameAnalytics\Models\DeviceStatistic;
use App\Features\GameAnalytics\Models\PlayerStatistic;
use App\Features\GameAnalytics\Models\RetentionStatistic;
use App\Features\GameAnalytics\Models\RevenueStatistic;
use App\Features\GameAnalytics\Models\RoomStatistic;
use App\Features\GameAnalytics\Repositories\Contracts\AnalyticsRepository;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentAnalyticsRepository implements AnalyticsRepository
{
    public function appendEvent(NormalizedAnalyticsEvent $event): ?AnalyticsEvent
    {
        if ($event->idempotencyKey !== null) {
            $existing = $this->findByIdempotencyKey($event->idempotencyKey);
            if ($existing !== null) {
                return $existing;
            }
        }

        try {
            return AnalyticsEvent::query()->create($event->toPersistenceArray());
        } catch (QueryException $exception) {
            if ($event->idempotencyKey !== null && $this->isUniqueViolation($exception)) {
                return $this->findByIdempotencyKey($event->idempotencyKey);
            }

            throw $exception;
        }
    }

    public function findByIdempotencyKey(string $key): ?AnalyticsEvent
    {
        return AnalyticsEvent::query()->where('idempotency_key', $key)->first();
    }

    public function aggregateRawForHour(CarbonInterface $hourStart, CarbonInterface $hourEnd): Collection
    {
        return $this->aggregateRawBetween($hourStart, $hourEnd);
    }

    public function aggregateRawForDay(CarbonInterface $dayStart, CarbonInterface $dayEnd): Collection
    {
        return $this->aggregateRawBetween($dayStart, $dayEnd);
    }

    public function upsertHourly(array $attributes, array $values): void
    {
        AnalyticsHourly::query()->updateOrCreate($this->nullSafeAttributes($attributes), $values);
    }

    public function upsertDaily(array $attributes, array $values): void
    {
        AnalyticsDaily::query()->updateOrCreate($this->nullSafeAttributes($attributes), $values);
    }

    public function upsertMonthly(array $attributes, array $values): void
    {
        AnalyticsMonthly::query()->updateOrCreate($this->nullSafeAttributes($attributes), $values);
    }

    public function upsertPlayerStatistic(array $attributes, array $values): void
    {
        PlayerStatistic::query()->updateOrCreate($this->nullSafeAttributes($attributes), $values);
    }

    public function upsertRoomStatistic(array $attributes, array $values): void
    {
        RoomStatistic::query()->updateOrCreate($this->nullSafeAttributes($attributes), $values);
    }

    public function upsertRevenueStatistic(array $attributes, array $values): void
    {
        RevenueStatistic::query()->updateOrCreate($this->nullSafeAttributes($attributes), $values);
    }

    public function upsertRetentionStatistic(array $attributes, array $values): void
    {
        RetentionStatistic::query()->updateOrCreate($this->nullSafeAttributes($attributes), $values);
    }

    public function upsertCountryStatistic(array $attributes, array $values): void
    {
        CountryStatistic::query()->updateOrCreate($this->nullSafeAttributes($attributes), $values);
    }

    public function upsertDeviceStatistic(array $attributes, array $values): void
    {
        DeviceStatistic::query()->updateOrCreate($this->nullSafeAttributes($attributes), $values);
    }

    public function storeSnapshot(string $type, ?string $periodKey, array $payload, ?array $metadata = null): AnalyticsSnapshot
    {
        return AnalyticsSnapshot::query()->create([
            'snapshot_type' => $type,
            'period_key' => $periodKey,
            'payload' => $payload,
            'captured_at' => now(),
            'metadata' => $metadata,
        ]);
    }

    public function dailyStats(AnalyticsFilterData $filters): Collection
    {
        return $this->applyFilters(AnalyticsDaily::query(), $filters, 'stat_date')
            ->orderByDesc('stat_date')
            ->limit($filters->limit)
            ->get();
    }

    public function hourlyStats(AnalyticsFilterData $filters): Collection
    {
        return $this->applyFilters(AnalyticsHourly::query(), $filters, 'hour_start')
            ->orderByDesc('hour_start')
            ->limit($filters->limit)
            ->get();
    }

    public function revenueStats(AnalyticsFilterData $filters): Collection
    {
        return $this->applyFilters(RevenueStatistic::query(), $filters, 'stat_date')
            ->orderByDesc('stat_date')
            ->limit($filters->limit)
            ->get();
    }

    public function playerStats(AnalyticsFilterData $filters): Collection
    {
        return $this->applyFilters(PlayerStatistic::query(), $filters, 'stat_date')
            ->orderByDesc('games_played')
            ->limit($filters->limit)
            ->get();
    }

    public function retentionStats(AnalyticsFilterData $filters): Collection
    {
        $query = RetentionStatistic::query();
        if ($filters->from !== null) {
            $query->where('cohort_date', '>=', $filters->from->toDateString());
        }
        if ($filters->to !== null) {
            $query->where('cohort_date', '<=', $filters->to->toDateString());
        }

        return $query->orderByDesc('cohort_date')->limit($filters->limit)->get();
    }

    public function roomStats(AnalyticsFilterData $filters): Collection
    {
        $query = $this->applyFilters(RoomStatistic::query(), $filters, 'stat_date');
        if ($filters->roomType !== null) {
            $query->where('room_type', $filters->roomType);
        }

        return $query->orderByDesc('revenue')->limit($filters->limit)->get();
    }

    public function countryStats(AnalyticsFilterData $filters): Collection
    {
        return $this->applyFilters(CountryStatistic::query(), $filters, 'stat_date')
            ->orderByDesc('revenue')
            ->limit($filters->limit)
            ->get();
    }

    public function deviceStats(AnalyticsFilterData $filters): Collection
    {
        return $this->applyFilters(DeviceStatistic::query(), $filters, 'stat_date')
            ->orderByDesc('dau')
            ->limit($filters->limit)
            ->get();
    }

    public function latestSnapshot(string $type): ?AnalyticsSnapshot
    {
        return AnalyticsSnapshot::query()
            ->where('snapshot_type', $type)
            ->orderByDesc('captured_at')
            ->first();
    }

    /**
     * @return Collection<int, object>
     */
    private function aggregateRawBetween(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return AnalyticsEvent::query()
            ->select([
                'game_slug',
                'country_code',
                'device_platform',
                DB::raw('COUNT(*) as games_played'),
                DB::raw('COUNT(DISTINCT user_id) as unique_players'),
                DB::raw("SUM(CASE WHEN event LIKE '%start%' OR event LIKE '%started%' THEN 1 ELSE 0 END) as matches_started"),
                DB::raw("SUM(CASE WHEN event LIKE '%finish%' OR event LIKE '%finished%' OR event LIKE '%complete%' THEN 1 ELSE 0 END) as matches_finished"),
                DB::raw("SUM(CASE WHEN event LIKE '%reconnect%' THEN 1 ELSE 0 END) as reconnect_count"),
                DB::raw("SUM(CASE WHEN event LIKE '%abandon%' OR event LIKE '%cancel%' OR event LIKE '%expired%' THEN 1 ELSE 0 END) as abandoned_games"),
                DB::raw("SUM(CASE WHEN event LIKE '%win%' THEN 1 ELSE 0 END) as wins"),
                DB::raw("SUM(CASE WHEN event LIKE '%loss%' OR event LIKE '%lost%' THEN 1 ELSE 0 END) as losses"),
                DB::raw("SUM(CASE WHEN event LIKE '%jackpot%' THEN 1 ELSE 0 END) as jackpots"),
                DB::raw("SUM(CASE WHEN event LIKE '%bonus%' THEN 1 ELSE 0 END) as bonus_wins"),
                DB::raw("SUM(CASE WHEN event LIKE '%free%' THEN 1 ELSE 0 END) as free_plays"),
                DB::raw('COALESCE(SUM(coins_wagered), 0) as coins_wagered'),
                DB::raw('COALESCE(SUM(coins_won), 0) as coins_won'),
                DB::raw('COALESCE(SUM(coins_burned), 0) as coins_burned'),
                DB::raw('COALESCE(SUM(diamonds_consumed), 0) as diamonds_consumed'),
                DB::raw('COALESCE(SUM(revenue), 0) as revenue'),
            ])
            ->where(function (Builder $query) use ($from, $to): void {
                $query->whereBetween('occurred_at', [$from, $to])
                    ->orWhere(function (Builder $inner) use ($from, $to): void {
                        $inner->whereNull('occurred_at')->whereBetween('created_at', [$from, $to]);
                    });
            })
            ->groupBy('game_slug', 'country_code', 'device_platform')
            ->get();
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private function applyFilters(Builder $query, AnalyticsFilterData $filters, string $dateColumn): Builder
    {
        if ($filters->from !== null) {
            $query->where($dateColumn, '>=', $dateColumn === 'hour_start' ? $filters->from : $filters->from->toDateString());
        }
        if ($filters->to !== null) {
            $query->where($dateColumn, '<=', $dateColumn === 'hour_start' ? $filters->to : $filters->to->toDateString());
        }
        if ($filters->gameSlug !== null && $query->getModel()->isFillable('game_slug')) {
            $query->where('game_slug', $filters->gameSlug);
        }
        if ($filters->countryCode !== null && $query->getModel()->isFillable('country_code')) {
            $query->where('country_code', $filters->countryCode);
        }
        if ($filters->devicePlatform !== null && $query->getModel()->isFillable('device_platform')) {
            $query->where('device_platform', $filters->devicePlatform);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function nullSafeAttributes(array $attributes): array
    {
        // updateOrCreate treats null dimension keys as equality; keep explicit nulls.
        return $attributes;
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique')
            || str_contains($message, 'duplicate')
            || (string) $exception->getCode() === '23000';
    }
}
