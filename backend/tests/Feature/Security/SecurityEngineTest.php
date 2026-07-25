<?php

namespace Tests\Feature\Security;

use App\Features\Security\AntiCheat\Contracts\Scanner;
use App\Features\Security\DTO\DetectedSignal;
use App\Features\Security\DTO\SecurityEventData;
use App\Features\Security\Engine\SecurityEngine;
use App\Features\Security\Models\SecurityAlert;
use App\Features\Security\Models\SecurityBan;
use App\Features\Security\Models\SecurityFlag;
use App\Features\Security\Models\SecurityLog;
use App\Features\Security\Models\SecurityReview;
use App\Features\Security\Support\SecurityAction;
use App\Features\Security\Support\SecurityContext;
use App\Features\Security\Support\SecuritySeverity;
use App\Features\Security\Support\SecuritySignal;
use App\Features\Security\Support\SecuritySurface;
use App\Features\Users\Models\User;
use Carbon\CarbonImmutable;
use RuntimeException;

final class SecurityEngineTest extends SecurityTestCase
{
    public function test_a_clean_event_is_allowed_and_produces_no_alerts(): void
    {
        $user = User::factory()->create();

        $verdict = app(SecurityEngine::class)->analyze(
            $this->event('gift_sent', ['amount' => 10], [
                'user_id' => (string) $user->id,
                'surface' => SecuritySurface::GIFTS,
            ]),
        );

        $this->assertSame(SecurityAction::ALLOW, $verdict->action);
        $this->assertSame(0, SecurityAlert::query()->count());
    }

    public function test_machine_regular_cadence_is_detected_as_an_auto_clicker(): void
    {
        $user = User::factory()->create();
        $engine = app(SecurityEngine::class);
        $start = CarbonImmutable::parse('2026-01-01 10:00:00');

        $first = null;
        for ($i = 0; $i < 8 && $first === null; $i++) {
            $verdict = $engine->analyze($this->event(
                'game_played',
                ['round' => $i],
                ['user_id' => (string) $user->id, 'surface' => SecuritySurface::GAMES],
                $start->addMilliseconds($i * 500),
            ));

            if ($verdict->hasSignal(SecuritySignal::AUTO_CLICKER)) {
                $first = $verdict;
            }
        }

        $this->assertNotNull($first, 'perfectly regular cadence should trip the automation scanner');
        $this->assertSame(SecurityAction::THROTTLE, $first->action);

        $alert = SecurityAlert::query()->where('signal', SecuritySignal::AUTO_CLICKER)->firstOrFail();
        $this->assertSame((string) $user->id, $alert->user_id);
        $this->assertSame(SecuritySurface::GAMES, $alert->surface);
    }

    public function test_sustained_abuse_escalates_beyond_the_single_event_verdict(): void
    {
        $user = User::factory()->create();
        $engine = app(SecurityEngine::class);
        $start = CarbonImmutable::parse('2026-01-01 10:00:00');

        $last = null;
        for ($i = 0; $i < 20; $i++) {
            $last = $engine->analyze($this->event(
                'game_played',
                ['round' => $i],
                ['user_id' => (string) $user->id, 'surface' => SecuritySurface::GAMES],
                $start->addMilliseconds($i * 500),
            ));
        }

        $this->assertContains($last->action, [SecurityAction::SUSPEND, SecurityAction::BAN, SecurityAction::REVIEW]);
        $this->assertGreaterThan(
            1,
            SecurityFlag::query()->where('user_id', $user->id)->value('hit_count'),
        );
    }

    public function test_impossible_reaction_time_is_detected(): void
    {
        $user = User::factory()->create();

        $verdict = app(SecurityEngine::class)->analyze($this->event(
            'quiz_answered',
            ['reaction_ms' => 12],
            ['user_id' => (string) $user->id, 'surface' => SecuritySurface::GAMES],
        ));

        $this->assertTrue($verdict->hasSignal(SecuritySignal::IMPOSSIBLE_REACTION));
    }

    public function test_a_duplicate_settlement_is_treated_as_a_replay(): void
    {
        $user = User::factory()->create();
        $engine = app(SecurityEngine::class);

        $make = fn (): SecurityEventData => $this->event(
            'game_settled',
            ['settlement_id' => 'settle-7', 'amount' => 500],
            [
                'user_id' => (string) $user->id,
                'surface' => SecuritySurface::GAMES,
                'subject_id' => '22222222-2222-4222-8222-222222222222',
            ],
        );

        $engine->analyze($make());
        $verdict = $engine->analyze($make());

        $this->assertTrue(
            $verdict->hasSignal(SecuritySignal::REPLAY_ATTACK)
            || $verdict->hasSignal(SecuritySignal::DUPLICATE_SETTLEMENT),
        );
    }

    public function test_verdicts_are_recorded_as_alerts_flags_and_audit_entries(): void
    {
        $user = User::factory()->create();

        app(SecurityEngine::class)->registerScanner($this->scannerEmitting(
            SecuritySignal::WALLET_FRAUD,
            SecuritySeverity::CRITICAL,
            96.0,
        ));

        app(SecurityEngine::class)->analyze($this->event(
            'recharge_completed',
            ['amount' => 100000],
            ['user_id' => (string) $user->id, 'surface' => SecuritySurface::WALLET],
        ));

        $this->assertDatabaseHas('security_alerts', [
            'user_id' => $user->id,
            'signal' => SecuritySignal::WALLET_FRAUD,
        ]);
        $this->assertDatabaseHas('security_flags', [
            'user_id' => $user->id,
            'flag' => SecuritySignal::WALLET_FRAUD,
            'status' => SecurityFlag::STATUS_ACTIVE,
        ]);
        $ban = SecurityBan::query()->effective()->where('user_id', $user->id)->firstOrFail();
        $this->assertTrue($ban->is_automatic);
        $this->assertContains($ban->scope, [SecurityBan::SCOPE_WALLET, SecurityBan::SCOPE_PLATFORM]);
        $this->assertTrue(
            SecurityLog::query()->where('channel', SecurityLog::CHANNEL_ENGINE)->exists(),
        );
    }

    public function test_review_actions_open_a_manual_case_instead_of_enforcing(): void
    {
        $user = User::factory()->create();

        app(SecurityEngine::class)->registerScanner($this->scannerEmitting(
            SecuritySignal::REWARD_FARMING,
            SecuritySeverity::HIGH,
            90.0,
        ));

        $verdict = app(SecurityEngine::class)->analyze($this->event(
            'reward_claimed',
            [],
            ['user_id' => (string) $user->id, 'surface' => SecuritySurface::WALLET],
        ));

        $this->assertSame(SecurityAction::REVIEW, $verdict->action);
        $this->assertSame(1, SecurityReview::query()->pending()->count());
        $this->assertSame(0, SecurityBan::query()->count());
    }

    public function test_shadow_mode_records_findings_without_enforcing_them(): void
    {
        config(['security_platform.enforcement_enabled' => false]);
        $user = User::factory()->create();

        app(SecurityEngine::class)->registerScanner($this->scannerEmitting(
            SecuritySignal::WALLET_FRAUD,
            SecuritySeverity::CRITICAL,
            99.0,
        ));

        app(SecurityEngine::class)->analyze($this->event(
            'withdrawal_requested',
            [],
            ['user_id' => (string) $user->id, 'surface' => SecuritySurface::WALLET],
        ));

        $this->assertSame(1, SecurityAlert::query()->count());
        $this->assertSame(0, SecurityBan::query()->count());
    }

    public function test_a_new_detector_needs_no_change_to_the_engine_core(): void
    {
        $user = User::factory()->create();

        app(SecurityEngine::class)->registerScanner(new class implements Scanner
        {
            public function key(): string
            {
                return 'tests.custom';
            }

            public function supports(SecurityEventData $event): bool
            {
                return true;
            }

            public function scan(SecurityEventData $event, SecurityContext $context): array
            {
                return [DetectedSignal::make(
                    signal: 'tests.custom_signal',
                    severity: SecuritySeverity::HIGH,
                    confidence: 88.0,
                    title: 'Custom detector fired',
                    userId: $event->userId,
                )];
            }
        });

        $verdict = app(SecurityEngine::class)->analyze($this->event(
            'anything_happened',
            [],
            ['user_id' => (string) $user->id],
        ));

        $this->assertTrue($verdict->hasSignal('tests.custom_signal'));
    }

    public function test_one_failing_detector_does_not_stop_the_others(): void
    {
        $user = User::factory()->create();
        $engine = app(SecurityEngine::class);

        $engine->registerScanner(new class implements Scanner
        {
            public function key(): string
            {
                return 'tests.broken';
            }

            public function supports(SecurityEventData $event): bool
            {
                return true;
            }

            public function scan(SecurityEventData $event, SecurityContext $context): array
            {
                throw new RuntimeException('detector exploded');
            }
        });
        $engine->registerScanner($this->scannerEmitting(
            SecuritySignal::BOT_PLAY,
            SecuritySeverity::HIGH,
            91.0,
        ));

        $verdict = $engine->analyze($this->event(
            'game_played',
            [],
            ['user_id' => (string) $user->id, 'surface' => SecuritySurface::GAMES],
        ));

        $this->assertTrue($verdict->hasSignal(SecuritySignal::BOT_PLAY));
        $this->assertDatabaseHas('security_logs', ['action' => 'scanner.failed']);
    }

    public function test_the_engine_never_writes_outside_its_own_tables(): void
    {
        $user = User::factory()->create();
        $walletBefore = $user->wallet?->getAttributes();

        app(SecurityEngine::class)->registerScanner($this->scannerEmitting(
            SecuritySignal::WALLET_FRAUD,
            SecuritySeverity::CRITICAL,
            99.0,
        ));

        app(SecurityEngine::class)->analyze($this->event(
            'transaction_completed',
            ['amount' => 5000],
            ['user_id' => (string) $user->id, 'surface' => SecuritySurface::WALLET],
        ));

        $user->refresh();

        $this->assertSame($walletBefore, $user->wallet?->fresh()?->getAttributes());
        $this->assertSame(User::STATUS_ACTIVE, $user->status);
    }

    private function scannerEmitting(string $signal, string $severity, float $confidence): Scanner
    {
        return new class($signal, $severity, $confidence) implements Scanner
        {
            public function __construct(
                private readonly string $signal,
                private readonly string $severity,
                private readonly float $confidence,
            ) {}

            public function key(): string
            {
                return 'tests.'.$this->signal;
            }

            public function supports(SecurityEventData $event): bool
            {
                return true;
            }

            public function scan(SecurityEventData $event, SecurityContext $context): array
            {
                return [DetectedSignal::make(
                    signal: $this->signal,
                    severity: $this->severity,
                    confidence: $this->confidence,
                    title: 'Injected '.$this->signal,
                    surface: $event->surface,
                    userId: $event->userId,
                )];
            }
        };
    }
}
