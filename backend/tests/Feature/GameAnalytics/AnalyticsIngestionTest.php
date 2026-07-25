<?php

namespace Tests\Feature\GameAnalytics;

use App\Features\GameAnalytics\DTO\NormalizedAnalyticsEvent;
use App\Features\GameAnalytics\Jobs\PersistAnalyticsEventJob;
use App\Features\GameAnalytics\Models\AnalyticsEvent;
use App\Features\GameAnalytics\Services\AnalyticsCollector;
use App\Features\GameAnalytics\Support\EventNormalizer;
use App\Features\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;

final class AnalyticsIngestionTest extends GameAnalyticsTestCase
{
    public function test_normalizer_builds_immutable_event_without_domain_assumptions(): void
    {
        $user = User::factory()->create();
        $event = new class($user->id)
        {
            public function __construct(public readonly string $user_id) {}
        };

        $normalized = app(EventNormalizer::class)->normalize($event::class, [$event]);

        $this->assertInstanceOf(NormalizedAnalyticsEvent::class, $normalized);
        $this->assertSame($user->id, $normalized->userId);
        $this->assertNotEmpty($normalized->idempotencyKey);
        $this->assertArrayNotHasKey('password', $normalized->payload);
    }

    public function test_collector_queues_persist_job_on_analytics_queue(): void
    {
        Queue::fake();

        $normalized = new NormalizedAnalyticsEvent(
            eventName: 'demo_played',
            source: 'games',
            category: 'play',
            idempotencyKey: 'abc123',
            userId: null,
            gameId: null,
            gameSlug: 'lucky-wheel',
            roomType: null,
            roomId: null,
            countryCode: 'TR',
            devicePlatform: 'ios',
            coinsWagered: '10.0000',
            coinsWon: '0.0000',
            coinsBurned: '0.0000',
            diamondsConsumed: '0.0000',
            revenue: '10.0000',
            payload: ['bet' => 10],
            metadata: [],
            occurredAt: CarbonImmutable::now(),
        );

        app(AnalyticsCollector::class)->queueNormalized($normalized);

        Queue::assertPushedOn('analytics', PersistAnalyticsEventJob::class);
    }

    public function test_persist_is_idempotent_by_key(): void
    {
        $normalized = new NormalizedAnalyticsEvent(
            eventName: 'spin_finished',
            source: 'games',
            category: 'finish',
            idempotencyKey: 'idem-1',
            userId: null,
            gameId: null,
            gameSlug: 'slot-machine',
            roomType: null,
            roomId: null,
            countryCode: null,
            devicePlatform: null,
            coinsWagered: '5.0000',
            coinsWon: '20.0000',
            coinsBurned: '0.0000',
            diamondsConsumed: '0.0000',
            revenue: '5.0000',
            payload: [],
            metadata: [],
            occurredAt: CarbonImmutable::parse('2026-08-04 12:00:00'),
        );

        $collector = app(AnalyticsCollector::class);
        $collector->persist($normalized);
        $collector->persist($normalized);

        $this->assertSame(1, AnalyticsEvent::query()->where('idempotency_key', 'idem-1')->count());
        $this->assertDatabaseHas('game_analytics_events', [
            'idempotency_key' => 'idem-1',
            'game_slug' => 'slot-machine',
            'event' => 'spin_finished',
        ]);
    }

    public function test_wildcard_listener_ingests_domain_event_via_queue_path(): void
    {
        $user = User::factory()->create();
        $domainEvent = new class($user->id, 50)
        {
            public function __construct(
                public readonly string $user_id,
                public readonly int $bet_coins,
            ) {}
        };

        event($domainEvent);

        $this->assertTrue(
            AnalyticsEvent::query()->where('user_id', $user->id)->exists(),
            'Expected wildcard analytics collector to persist a raw event',
        );
    }
}
