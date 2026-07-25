<?php

namespace Tests\Feature\Security;

use App\Features\Security\Jobs\AnalyzeSecurityEventJob;
use App\Features\Security\Jobs\PurgeSecurityDataJob;
use App\Features\Security\Jobs\RecalculateRiskScoreJob;
use App\Features\Security\Models\SecurityAlert;
use App\Features\Security\Models\SecurityEvent;
use App\Features\Security\Models\SecurityLog;
use App\Features\Security\Models\SecurityNetwork;
use App\Features\Security\Models\SecuritySession;
use App\Features\Security\Services\SecurityCollector;
use App\Features\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

final class SecurityQueueTest extends SecurityTestCase
{
    public function test_no_analysis_happens_on_the_request_path(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        app(SecurityCollector::class)->collectFromDomainEvent(
            'App\\Features\\Games\\Events\\GameFinished',
            [['user_id' => (string) $user->id]],
        );

        // Nothing has been persisted or scored yet: the worker does that.
        $this->assertSame(0, SecurityEvent::query()->count());
        $this->assertSame(0, SecurityAlert::query()->count());
        Queue::assertPushed(AnalyzeSecurityEventJob::class, 1);
    }

    public function test_ingestion_adds_only_a_bounded_amount_of_work_to_the_caller(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        DB::enableQueryLog();
        app(SecurityCollector::class)->collectFromDomainEvent(
            'App\\Features\\Wallet\\Events\\TransactionCompleted',
            [['user_id' => (string) $user->id, 'amount' => 25]],
        );
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame([], $queries, 'normalization must not touch the database');
    }

    public function test_the_worker_persists_and_analyzes(): void
    {
        $user = User::factory()->create();

        app(SecurityCollector::class)->collectFromDomainEvent(
            'App\\Features\\Games\\Events\\GameFinished',
            [['user_id' => (string) $user->id]],
        );

        $event = SecurityEvent::query()->firstOrFail();
        $this->assertTrue($event->analyzed);
    }

    public function test_the_analysis_job_is_retryable_and_bounded(): void
    {
        $job = new AnalyzeSecurityEventJob($this->event('anything'));

        $this->assertGreaterThan(1, $job->tries);
        $this->assertGreaterThan(0, $job->timeout);
        $this->assertSame('security', $job->queue);
    }

    public function test_the_recalculate_job_targets_the_security_queue(): void
    {
        Queue::fake();

        RecalculateRiskScoreJob::dispatch((string) User::factory()->create()->id);

        Queue::assertPushed(
            RecalculateRiskScoreJob::class,
            static fn (RecalculateRiskScoreJob $job): bool => $job->queue === 'security',
        );
    }

    public function test_the_purge_job_enforces_every_retention_window(): void
    {
        $user = User::factory()->create();
        $old = CarbonImmutable::now()->subYears(3);

        SecurityEvent::query()->create([
            'user_id' => $user->id,
            'event' => 'ancient',
            'source' => 'tests',
            'surface' => 'platform',
            'amount' => 0,
            'occurred_at' => $old,
            'created_at' => $old,
        ]);
        SecurityAlert::query()->create([
            'user_id' => $user->id,
            'signal' => 'tests.ancient',
            'title' => 'Ancient alert',
            'severity' => 'low',
            'surface' => 'platform',
            'action' => 'warn',
            'status' => SecurityAlert::STATUS_RESOLVED,
            'confidence' => 10,
        ])->forceFill(['created_at' => $old])->save();
        SecuritySession::query()->create([
            'user_id' => $user->id,
            'session_hash' => hash('sha256', 'ancient'),
            'status' => SecuritySession::STATUS_ENDED,
            'last_seen_at' => $old,
        ]);
        SecurityNetwork::query()->create([
            'user_id' => $user->id,
            'ip_hash' => hash('sha256', 'ancient-ip'),
            'last_seen_at' => $old,
        ]);

        dispatch_sync(new PurgeSecurityDataJob);

        $this->assertSame(0, SecurityEvent::query()->count());
        $this->assertSame(0, SecurityAlert::query()->count());
        $this->assertSame(0, SecuritySession::query()->count());
        $this->assertSame(0, SecurityNetwork::query()->count());
        $this->assertTrue(
            SecurityLog::query()->where('channel', SecurityLog::CHANNEL_RETENTION)->exists(),
            'retention runs must be auditable',
        );
    }

    public function test_the_purge_job_keeps_data_inside_the_retention_window(): void
    {
        $user = User::factory()->create();

        SecurityEvent::query()->create([
            'user_id' => $user->id,
            'event' => 'recent',
            'source' => 'tests',
            'surface' => 'platform',
            'amount' => 0,
            'occurred_at' => CarbonImmutable::now()->subDay(),
            'created_at' => CarbonImmutable::now()->subDay(),
        ]);

        dispatch_sync(new PurgeSecurityDataJob);

        $this->assertSame(1, SecurityEvent::query()->count());
    }

    public function test_the_purge_command_queues_the_job(): void
    {
        Queue::fake();

        $this->artisan('security:purge')->assertSuccessful();

        Queue::assertPushed(PurgeSecurityDataJob::class);
    }
}
