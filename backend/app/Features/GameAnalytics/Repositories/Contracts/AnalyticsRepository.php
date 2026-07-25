<?php

namespace App\Features\GameAnalytics\Repositories\Contracts;

use App\Features\GameAnalytics\DTO\AnalyticsFilterData;
use App\Features\GameAnalytics\DTO\NormalizedAnalyticsEvent;
use App\Features\GameAnalytics\Models\AnalyticsDaily;
use App\Features\GameAnalytics\Models\AnalyticsEvent;
use App\Features\GameAnalytics\Models\AnalyticsHourly;
use App\Features\GameAnalytics\Models\AnalyticsSnapshot;
use App\Features\GameAnalytics\Models\CountryStatistic;
use App\Features\GameAnalytics\Models\DeviceStatistic;
use App\Features\GameAnalytics\Models\PlayerStatistic;
use App\Features\GameAnalytics\Models\RetentionStatistic;
use App\Features\GameAnalytics\Models\RevenueStatistic;
use App\Features\GameAnalytics\Models\RoomStatistic;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface AnalyticsRepository
{
    public function appendEvent(NormalizedAnalyticsEvent $event): ?AnalyticsEvent;

    public function findByIdempotencyKey(string $key): ?AnalyticsEvent;

    /**
     * @return Collection<int, object>
     */
    public function aggregateRawForHour(CarbonInterface $hourStart, CarbonInterface $hourEnd): Collection;

    /**
     * @return Collection<int, object>
     */
    public function aggregateRawForDay(CarbonInterface $dayStart, CarbonInterface $dayEnd): Collection;

    public function upsertHourly(array $attributes, array $values): void;

    public function upsertDaily(array $attributes, array $values): void;

    public function upsertMonthly(array $attributes, array $values): void;

    public function upsertPlayerStatistic(array $attributes, array $values): void;

    public function upsertRoomStatistic(array $attributes, array $values): void;

    public function upsertRevenueStatistic(array $attributes, array $values): void;

    public function upsertRetentionStatistic(array $attributes, array $values): void;

    public function upsertCountryStatistic(array $attributes, array $values): void;

    public function upsertDeviceStatistic(array $attributes, array $values): void;

    public function storeSnapshot(string $type, ?string $periodKey, array $payload, ?array $metadata = null): AnalyticsSnapshot;

    /**
     * @return Collection<int, AnalyticsDaily>
     */
    public function dailyStats(AnalyticsFilterData $filters): Collection;

    /**
     * @return Collection<int, AnalyticsHourly>
     */
    public function hourlyStats(AnalyticsFilterData $filters): Collection;

    /**
     * @return Collection<int, RevenueStatistic>
     */
    public function revenueStats(AnalyticsFilterData $filters): Collection;

    /**
     * @return Collection<int, PlayerStatistic>
     */
    public function playerStats(AnalyticsFilterData $filters): Collection;

    /**
     * @return Collection<int, RetentionStatistic>
     */
    public function retentionStats(AnalyticsFilterData $filters): Collection;

    /**
     * @return Collection<int, RoomStatistic>
     */
    public function roomStats(AnalyticsFilterData $filters): Collection;

    /**
     * @return Collection<int, CountryStatistic>
     */
    public function countryStats(AnalyticsFilterData $filters): Collection;

    /**
     * @return Collection<int, DeviceStatistic>
     */
    public function deviceStats(AnalyticsFilterData $filters): Collection;

    public function latestSnapshot(string $type): ?AnalyticsSnapshot;
}
