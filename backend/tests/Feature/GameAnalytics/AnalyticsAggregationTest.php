<?php

namespace Tests\Feature\GameAnalytics;

use App\Features\GameAnalytics\Jobs\AggregateDailyAnalyticsJob;
use App\Features\GameAnalytics\Models\AnalyticsDaily;
use App\Features\GameAnalytics\Models\AnalyticsEvent;
use App\Features\GameAnalytics\Models\RevenueStatistic;
use App\Features\GameAnalytics\Services\AnalyticsAggregator;
use App\Features\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

final class AnalyticsAggregationTest extends GameAnalyticsTestCase
{
    public function test_daily_aggregation_builds_statistics_from_raw_events(): void
    {
        $user = User::factory()->create();
        $day = CarbonImmutable::parse('2026-08-03');

        AnalyticsEvent::query()->create([
            'idempotency_key' => (string) Str::uuid(),
            'user_id' => $user->id,
            'game_slug' => 'lucky-dice',
            'event' => 'lucky_dice_played',
            'source' => 'games',
            'category' => 'play',
            'country_code' => 'TR',
            'device_platform' => 'android',
            'coins_wagered' => 100,
            'coins_won' => 40,
            'revenue' => 100,
            'payload' => [],
            'metadata' => [],
            'created_at' => $day->setTime(10, 0),
            'occurred_at' => $day->setTime(10, 0),
        ]);

        AnalyticsEvent::query()->create([
            'idempotency_key' => (string) Str::uuid(),
            'user_id' => $user->id,
            'game_slug' => 'lucky-dice',
            'event' => 'lucky_dice_won',
            'source' => 'games',
            'category' => 'win',
            'country_code' => 'TR',
            'device_platform' => 'android',
            'coins_wagered' => 0,
            'coins_won' => 200,
            'revenue' => 0,
            'payload' => [],
            'metadata' => [],
            'created_at' => $day->setTime(11, 0),
            'occurred_at' => $day->setTime(11, 0),
        ]);

        $rows = app(AnalyticsAggregator::class)->aggregateDay($day);

        $this->assertGreaterThan(0, $rows);
        $this->assertTrue(
            AnalyticsDaily::query()
                ->whereDate('stat_date', $day->toDateString())
                ->where('game_slug', 'lucky-dice')
                ->where('country_code', 'TR')
                ->where('device_platform', 'android')
                ->exists(),
        );
        $this->assertTrue(RevenueStatistic::query()->whereDate('stat_date', $day->toDateString())->exists());
        $this->assertTrue(AnalyticsDaily::query()->where('game_slug', 'lucky-dice')->where('games_played', '>=', 1)->exists());
    }

    public function test_aggregate_daily_job_is_queued_on_analytics_queue(): void
    {
        Queue::fake();

        AggregateDailyAnalyticsJob::dispatch('2026-08-03')
            ->onConnection('sync')
            ->onQueue('analytics');

        Queue::assertPushedOn('analytics', AggregateDailyAnalyticsJob::class);
    }
}
