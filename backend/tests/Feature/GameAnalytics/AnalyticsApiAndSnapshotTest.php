<?php

namespace Tests\Feature\GameAnalytics;

use App\Features\GameAnalytics\Models\AnalyticsDaily;
use App\Features\GameAnalytics\Models\AnalyticsSnapshot;
use App\Features\GameAnalytics\Models\PlayerStatistic;
use App\Features\GameAnalytics\Models\RetentionStatistic;
use App\Features\GameAnalytics\Models\RevenueStatistic;
use App\Features\GameAnalytics\Services\SnapshotService;
use App\Features\Users\Models\User;

final class AnalyticsApiAndSnapshotTest extends GameAnalyticsTestCase
{
    public function test_analytics_endpoints_return_payloads_for_authenticated_users(): void
    {
        $this->actingUser();
        $this->seedSampleStats();

        foreach ([
            '/api/v1/analytics/overview',
            '/api/v1/analytics/revenue',
            '/api/v1/analytics/games',
            '/api/v1/analytics/players',
            '/api/v1/analytics/retention',
            '/api/v1/analytics/live',
            '/api/v1/analytics/agencies',
            '/api/v1/analytics/vip',
        ] as $uri) {
            $this->getJson($uri)->assertOk()->assertJsonStructure(['data']);
        }
    }

    public function test_guests_cannot_access_analytics(): void
    {
        $this->getJson('/api/v1/analytics/overview')->assertUnauthorized();
    }

    public function test_snapshot_service_persists_executive_snapshot(): void
    {
        $this->seedSampleStats();

        $result = app(SnapshotService::class)->capture('executive');

        $this->assertArrayHasKey('id', $result);
        $this->assertTrue(AnalyticsSnapshot::query()->where('snapshot_type', 'executive')->exists());
    }

    private function seedSampleStats(): void
    {
        $user = User::factory()->create();

        AnalyticsDaily::query()->create([
            'stat_date' => now()->toDateString(),
            'game_slug' => 'quiz',
            'country_code' => 'US',
            'device_platform' => 'ios',
            'games_played' => 12,
            'unique_players' => 5,
            'dau' => 5,
            'revenue' => 250,
            'coins_wagered' => 400,
            'coins_won' => 120,
            'rtp' => 0.3,
            'house_edge' => 0.7,
            'arpu' => 50,
            'peak_ccu' => 3,
        ]);

        RevenueStatistic::query()->create([
            'stat_date' => now()->toDateString(),
            'game_slug' => 'quiz',
            'source' => 'games',
            'gross_revenue' => 250,
            'net_revenue' => 130,
            'coins_wagered' => 400,
            'coins_won' => 120,
            'paying_users' => 5,
            'arpu' => 50,
            'arppu' => 50,
        ]);

        PlayerStatistic::query()->create([
            'user_id' => $user->id,
            'game_slug' => 'quiz',
            'stat_date' => now()->toDateString(),
            'games_played' => 4,
            'wins' => 2,
            'losses' => 2,
            'coins_wagered' => 80,
            'coins_won' => 40,
            'revenue_contributed' => 80,
        ]);

        RetentionStatistic::query()->create([
            'cohort_date' => now()->subDay()->toDateString(),
            'cohort_type' => 'daily',
            'cohort_key' => 'all',
            'cohort_size' => 10,
            'retained_d1' => 4,
            'retained_d7' => 2,
            'retained_d30' => 1,
            'retention_d1_rate' => 0.4,
            'retention_d7_rate' => 0.2,
            'retention_d30_rate' => 0.1,
        ]);
    }
}
