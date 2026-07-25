<?php

namespace Tests\Feature\Security;

use App\Features\Security\DTO\SecurityEventData;
use App\Features\Security\SecurityServiceProvider;
use App\Features\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

abstract class SecurityTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(SecurityServiceProvider::class);
        $this->withoutMiddleware(ThrottleRequests::class);

        config([
            'security_platform.enabled' => true,
            'security_platform.enforcement_enabled' => true,
            'security_platform.queue_connection' => 'sync',
            'security_platform.queue' => 'security',
            'security_platform.cache_store' => 'array',
            'security_platform.realtime.broadcast_throttle_seconds' => 0,
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

    /**
     * Builds a normalized event directly, bypassing the wildcard listener so a
     * test can exercise one detector in isolation.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $overrides
     */
    protected function event(
        string $name,
        array $payload = [],
        array $overrides = [],
        ?CarbonImmutable $occurredAt = null,
    ): SecurityEventData {
        return new SecurityEventData(
            eventName: $name,
            source: $overrides['source'] ?? 'tests',
            surface: $overrides['surface'] ?? 'platform',
            category: $overrides['category'] ?? 'event',
            idempotencyKey: $overrides['idempotency_key'] ?? hash('sha256', $name.serialize($payload).uniqid('', true)),
            userId: $overrides['user_id'] ?? null,
            subjectId: $overrides['subject_id'] ?? null,
            subjectType: $overrides['subject_type'] ?? null,
            deviceHash: $overrides['device_hash'] ?? null,
            sessionHash: $overrides['session_hash'] ?? null,
            ipHash: $overrides['ip_hash'] ?? null,
            countryCode: $overrides['country_code'] ?? null,
            platform: $overrides['platform'] ?? 'android',
            amount: $overrides['amount'] ?? '0.0000',
            currency: $overrides['currency'] ?? null,
            payload: $payload,
            metadata: $overrides['metadata'] ?? [],
            occurredAt: $occurredAt ?? CarbonImmutable::now(),
        );
    }
}
