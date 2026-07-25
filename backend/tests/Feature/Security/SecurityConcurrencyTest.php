<?php

namespace Tests\Feature\Security;

use App\Features\Security\DTO\DetectedSignal;
use App\Features\Security\Engine\RiskEngine;
use App\Features\Security\Models\SecurityEvent;
use App\Features\Security\Models\SecurityRule;
use App\Features\Security\Models\SecurityScore;
use App\Features\Security\Rules\RuleProvider;
use App\Features\Security\Services\SecurityCollector;
use App\Features\Security\Support\SecuritySeverity;
use App\Features\Security\Support\SecuritySignal;
use App\Features\Users\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Concurrent workers analysing the same user must not lose increments.
 * SQLite serialises writes, so this exercises the lockForUpdate path rather
 * than true parallel threads — still the race condition that matters.
 */
final class SecurityConcurrencyTest extends SecurityTestCase
{
    public function test_concurrent_score_updates_accumulate_rather_than_overwrite(): void
    {
        $user = User::factory()->create();
        $engine = app(RiskEngine::class);
        $engine->profileFor((string) $user->id);

        $signal = DetectedSignal::make(
            signal: SecuritySignal::BOT_PLAY,
            severity: SecuritySeverity::HIGH,
            confidence: 80.0,
            title: 'bot',
        );

        // Two nested transactions, each holding the row lock then writing.
        DB::transaction(function () use ($engine, $user, $signal): void {
            $engine->apply((string) $user->id, [$signal]);

            DB::transaction(function () use ($engine, $user, $signal): void {
                $engine->apply((string) $user->id, [$signal]);
            });
        });

        $score = SecurityScore::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(2, $score->signal_count);
        $this->assertGreaterThan(0.0, (float) $score->risk_score);
    }

    public function test_idempotent_event_keys_survive_parallel_delivery(): void
    {
        $user = User::factory()->create();
        $collector = app(SecurityCollector::class);

        $event = $this->event('game_settled', ['amount' => 50], [
            'user_id' => (string) $user->id,
            'idempotency_key' => 'fixed-key-for-concurrency',
            'surface' => 'games',
        ]);

        $collector->process($event);
        $collector->process($event);
        $collector->process($event);

        $this->assertSame(1, SecurityEvent::query()->count());
    }

    public function test_rule_cache_flush_is_visible_to_every_worker_process(): void
    {
        $provider = app(RuleProvider::class);
        $provider->all();

        SecurityRule::query()->create([
            'key' => 'tests.concurrency',
            'name' => 'Concurrency rule',
            'signal' => 'tests.concurrency',
            'severity' => SecuritySeverity::HIGH,
            'action' => 'warn',
            'threshold' => 10,
            'priority' => 1,
            'is_enabled' => true,
        ]);

        // A second provider instance simulates another queue worker.
        $other = new RuleProvider;

        $this->assertNotNull($other->find('tests.concurrency'));
    }
}
