<?php

namespace Tests\Feature\Security;

use App\Features\Security\DTO\DetectedSignal;
use App\Features\Security\Engine\RiskEngine;
use App\Features\Security\Engine\SecurityEngine;
use App\Features\Security\Jobs\AnalyzeSecurityEventJob;
use App\Features\Security\Models\SecurityEvent;
use App\Features\Security\Models\SecurityScore;
use App\Features\Security\Services\SecurityCollector;
use App\Features\Security\Services\SecurityQueryService;
use App\Features\Security\Support\SecuritySeverity;
use App\Features\Security\Support\SecuritySignal;
use App\Features\Security\Support\SecuritySurface;
use App\Features\Security\Support\Statistics;
use App\Features\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * These are budget tests, not benchmarks. They fail if the hot paths regress
 * into per-event database work, which is what would break at 100k concurrent
 * users — not the absolute wall-clock numbers, which vary by machine.
 */
final class SecurityPerformanceTest extends SecurityTestCase
{
    public function test_ingestion_cost_does_not_grow_with_event_volume(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $collector = app(SecurityCollector::class);

        DB::enableQueryLog();
        for ($i = 0; $i < 500; $i++) {
            $collector->collectFromDomainEvent('App\\Features\\Games\\Events\\GamePlayed', [[
                'user_id' => (string) $user->id,
                'round' => $i,
            ]]);
        }
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(0, $queries, '500 ingested events must cost zero queries on the request path');
        Queue::assertPushed(AnalyzeSecurityEventJob::class, 500);
    }

    public function test_analysis_query_count_per_event_stays_bounded(): void
    {
        $user = User::factory()->create();
        $engine = app(SecurityEngine::class);

        // Warm any first-run caches so we measure steady state.
        $engine->analyze($this->event('game_played', [], [
            'user_id' => (string) $user->id,
            'surface' => SecuritySurface::GAMES,
        ]));

        DB::enableQueryLog();
        $engine->analyze($this->event('game_played', [], [
            'user_id' => (string) $user->id,
            'surface' => SecuritySurface::GAMES,
        ]));
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            25,
            $queries,
            "analysing one clean event took {$queries} queries; detectors should use the cache scratchpad",
        );
    }

    public function test_detectors_use_constant_memory_regardless_of_history_length(): void
    {
        $user = User::factory()->create();
        $engine = app(SecurityEngine::class);
        $start = CarbonImmutable::parse('2026-01-01 00:00:00');

        $before = memory_get_usage();
        for ($i = 0; $i < 400; $i++) {
            $engine->analyze($this->event(
                'game_played',
                ['round' => $i],
                ['user_id' => (string) $user->id, 'surface' => SecuritySurface::GAMES],
                $start->addMilliseconds($i * 137),
            ));
        }
        $growth = memory_get_usage() - $before;

        $this->assertLessThan(
            32 * 1024 * 1024,
            $growth,
            'sliding-window detectors must not accumulate unbounded state',
        );
    }

    public function test_score_updates_stay_a_single_row_operation(): void
    {
        $user = User::factory()->create();
        $engine = app(RiskEngine::class);
        $engine->profileFor((string) $user->id);

        DB::enableQueryLog();
        $engine->apply((string) $user->id, [
            DetectedSignal::make(
                signal: SecuritySignal::BOT_PLAY,
                severity: SecuritySeverity::HIGH,
                confidence: 80.0,
                title: 'bot',
            ),
        ]);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(8, $queries);
        $this->assertSame(1, SecurityScore::query()->count());
    }

    public function test_history_reads_are_indexed_and_bounded(): void
    {
        $user = User::factory()->create();

        $rows = [];
        for ($i = 0; $i < 300; $i++) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'event' => 'bulk_event',
                'source' => 'tests',
                'surface' => 'games',
                'amount' => 0,
                'occurred_at' => CarbonImmutable::now()->subMinutes($i),
                'created_at' => CarbonImmutable::now()->subMinutes($i),
            ];
        }
        SecurityEvent::query()->insert($rows);

        DB::enableQueryLog();
        $history = app(SecurityQueryService::class)
            ->history((string) $user->id, 50);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(50, $history['events']);
        $this->assertLessThanOrEqual(2, $queries);
    }

    public function test_statistics_helpers_are_allocation_light(): void
    {
        $samples = array_map(static fn (int $i): float => 500.0 + ($i % 7), range(1, 5000));

        $before = memory_get_usage();
        Statistics::mean($samples);
        Statistics::stdDev($samples);
        Statistics::coefficientOfVariation($samples);
        $growth = memory_get_usage() - $before;

        $this->assertLessThan(1024 * 1024, $growth);
    }
}
