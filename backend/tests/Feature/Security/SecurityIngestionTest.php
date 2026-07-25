<?php

namespace Tests\Feature\Security;

use App\Features\Security\Jobs\AnalyzeSecurityEventJob;
use App\Features\Security\Models\SecurityEvent;
use App\Features\Security\Services\SecurityCollector;
use App\Features\Security\Support\Fingerprint;
use App\Features\Security\Support\SecurityEventNormalizer;
use App\Features\Users\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

final class SecurityIngestionTest extends SecurityTestCase
{
    public function test_wildcard_listener_queues_analysis_for_any_domain_event(): void
    {
        Queue::fake();

        Event::dispatch('App\\Features\\Wallet\\Events\\TransactionCompleted', [[
            'user_id' => (string) User::factory()->create()->id,
            'amount' => 250,
        ]]);

        Queue::assertPushed(AnalyzeSecurityEventJob::class);
    }

    public function test_analysis_is_pushed_to_the_dedicated_queue(): void
    {
        Queue::fake();
        config(['security_platform.queue' => 'security', 'security_platform.queue_connection' => 'redis']);

        app(SecurityCollector::class)->collectFromDomainEvent('App\\Features\\Games\\Events\\GameFinished', [[
            'user_id' => (string) User::factory()->create()->id,
        ]]);

        Queue::assertPushed(
            AnalyzeSecurityEventJob::class,
            static fn (AnalyzeSecurityEventJob $job): bool => $job->queue === 'security'
                && $job->connection === 'redis',
        );
    }

    public function test_disabling_the_platform_stops_all_ingestion(): void
    {
        Queue::fake();
        config(['security_platform.enabled' => false]);

        Event::dispatch('App\\Features\\Gifts\\Events\\GiftSent', [['user_id' => 'x']]);

        Queue::assertNotPushed(AnalyzeSecurityEventJob::class);
    }

    public function test_security_own_events_are_ignored_so_ingestion_cannot_loop(): void
    {
        Queue::fake();

        Event::dispatch('App\\Features\\Security\\Events\\SecurityAlertRaised', [[]]);

        Queue::assertNotPushed(AnalyzeSecurityEventJob::class);
    }

    public function test_framework_noise_is_ignored(): void
    {
        Queue::fake();

        Event::dispatch('eloquent.saved: App\\Features\\Users\\Models\\User', [[]]);
        Event::dispatch('Illuminate\\Auth\\Events\\Login', [[]]);

        Queue::assertNotPushed(AnalyzeSecurityEventJob::class);
    }

    public function test_events_persist_with_hashed_identifiers_and_redacted_payloads(): void
    {
        $user = User::factory()->create();

        app(SecurityCollector::class)->collectFromDomainEvent('App\\Features\\Wallet\\Events\\RechargeCompleted', [[
            'user_id' => (string) $user->id,
            'amount' => 999,
            'currency' => 'USD',
            'device_id' => 'device-abc',
            'ip_address' => '203.0.113.10',
            'email' => 'player@example.com',
            'password' => 'hunter2',
            'access_token' => 'secret-token',
        ]]);

        $stored = SecurityEvent::query()->firstOrFail();

        $this->assertSame((string) $user->id, $stored->user_id);
        $this->assertSame('wallet', $stored->surface);
        $this->assertSame('999.0000', $stored->amount);
        $this->assertSame(Fingerprint::device('device-abc'), $stored->device_hash);
        $this->assertSame(Fingerprint::ip('203.0.113.10'), $stored->ip_hash);

        $this->assertArrayNotHasKey('email', $stored->payload);
        $this->assertArrayNotHasKey('password', $stored->payload);
        $this->assertArrayNotHasKey('access_token', $stored->payload);
        $this->assertArrayNotHasKey('ip_address', $stored->payload);
        $this->assertArrayNotHasKey('device_id', $stored->payload);
    }

    public function test_duplicate_delivery_of_the_same_event_is_stored_once(): void
    {
        $user = User::factory()->create();
        $normalizer = app(SecurityEventNormalizer::class);

        $normalized = $normalizer->normalize('App\\Features\\Games\\Events\\GameSettled', [[
            'id' => '11111111-1111-4111-8111-111111111111',
            'user_id' => (string) $user->id,
        ]]);

        $collector = app(SecurityCollector::class);
        $collector->process($normalized);
        $collector->process($normalized);

        $this->assertSame(1, SecurityEvent::query()->count());
    }

    public function test_a_broken_listener_never_breaks_the_emitting_module(): void
    {
        // Force a failure deep in ingestion; the dispatcher must not see it.
        config(['security_platform.queue_connection' => 'connection-that-does-not-exist']);

        Event::dispatch('App\\Features\\LiveStreaming\\Events\\ViewerJoined', [['user_id' => 'x']]);

        $this->assertTrue(true);
    }

    public function test_surface_is_inferred_from_the_emitting_module(): void
    {
        $normalizer = app(SecurityEventNormalizer::class);

        $this->assertSame(
            'games',
            $normalizer->normalize('App\\Features\\Games\\Events\\GameStarted', [[]])->surface,
        );
        $this->assertSame(
            'wallet',
            $normalizer->normalize('App\\Features\\Wallet\\Events\\WalletDebited', [[]])->surface,
        );
        $this->assertSame(
            'live',
            $normalizer->normalize('App\\Features\\LiveStreaming\\Events\\StreamStarted', [[]])->surface,
        );
        // An unmapped module is still observed, just as the generic surface.
        $this->assertSame(
            'platform',
            $normalizer->normalize('App\\Features\\FutureModule\\Events\\SomethingHappened', [[]])->surface,
        );
    }
}
