<?php

namespace Tests\Feature\GameAnalytics;

use App\Features\GameAnalytics\GameAnalyticsServiceProvider;
use App\Features\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

abstract class GameAnalyticsTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(GameAnalyticsServiceProvider::class);
        $this->withoutMiddleware(ThrottleRequests::class);

        config([
            'game_analytics.queue_connection' => 'sync',
            'game_analytics.queue' => 'analytics',
            'game_analytics.cache_store' => 'array',
        ]);
    }

    protected function actingUser(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'is_guest' => false,
            'status' => User::STATUS_ACTIVE,
        ], $attributes));

        Sanctum::actingAs($user);

        return $user;
    }

    protected function actingAdmin(): User
    {
        return $this->actingUser(['is_admin' => true]);
    }
}
