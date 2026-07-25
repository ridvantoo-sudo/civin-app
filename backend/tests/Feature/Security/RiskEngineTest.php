<?php

namespace Tests\Feature\Security;

use App\Features\Security\DTO\DetectedSignal;
use App\Features\Security\Engine\RiskEngine;
use App\Features\Security\Events\RiskScoreChanged;
use App\Features\Security\Jobs\RecalculateRiskScoreJob;
use App\Features\Security\Models\SecurityScore;
use App\Features\Security\Support\SecuritySeverity;
use App\Features\Security\Support\SecuritySignal;
use App\Features\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

final class RiskEngineTest extends SecurityTestCase
{
    public function test_every_user_starts_with_the_five_default_scores(): void
    {
        $user = User::factory()->create();

        $score = app(RiskEngine::class)->profileFor((string) $user->id);

        $this->assertSame(80.0, (float) $score->security_score);
        $this->assertSame(70.0, (float) $score->trust_score);
        $this->assertSame(0.0, (float) $score->risk_score);
        $this->assertSame(0.0, (float) $score->fraud_score);
        $this->assertSame(0.0, (float) $score->bot_score);
        $this->assertSame(SecurityScore::BAND_LOW, $score->band);
    }

    public function test_fraud_signals_raise_the_fraud_score_and_bot_signals_the_bot_score(): void
    {
        $user = User::factory()->create();
        $engine = app(RiskEngine::class);

        $engine->apply((string) $user->id, [
            $this->signal(SecuritySignal::WALLET_FRAUD, SecuritySeverity::CRITICAL, 90.0),
        ]);
        $score = $engine->apply((string) $user->id, [
            $this->signal(SecuritySignal::BOT_PLAY, SecuritySeverity::HIGH, 80.0),
        ]);

        $this->assertGreaterThan(0.0, (float) $score->fraud_score);
        $this->assertGreaterThan(0.0, (float) $score->bot_score);
        $this->assertGreaterThan(0.0, (float) $score->risk_score);
    }

    public function test_trust_and_security_move_inversely_to_risk(): void
    {
        $user = User::factory()->create();

        $score = app(RiskEngine::class)->apply((string) $user->id, [
            $this->signal(SecuritySignal::WALLET_FRAUD, SecuritySeverity::CRITICAL, 95.0),
        ]);

        $this->assertLessThan(70.0, (float) $score->trust_score);
        $this->assertLessThan(80.0, (float) $score->security_score);
    }

    public function test_a_single_detection_cannot_max_out_the_risk_score(): void
    {
        $user = User::factory()->create();

        $score = app(RiskEngine::class)->apply((string) $user->id, [
            $this->signal(SecuritySignal::WALLET_FRAUD, SecuritySeverity::CRITICAL, 100.0),
            $this->signal(SecuritySignal::REPLAY_ATTACK, SecuritySeverity::CRITICAL, 100.0),
            $this->signal(SecuritySignal::AUTO_CLICKER, SecuritySeverity::HIGH, 100.0),
        ]);

        $this->assertLessThanOrEqual(
            (float) config('security_platform.scores.max_event_delta'),
            (float) $score->risk_score,
        );
        $this->assertNull(app(RiskEngine::class)->escalationFor($score));
    }

    public function test_repeated_detections_escalate_through_the_bands(): void
    {
        $user = User::factory()->create();
        $engine = app(RiskEngine::class);

        $bands = [];
        for ($i = 0; $i < 6; $i++) {
            $score = $engine->apply((string) $user->id, [
                $this->signal(SecuritySignal::WALLET_FRAUD, SecuritySeverity::CRITICAL, 95.0),
            ]);
            $bands[] = $score->band;
        }

        $this->assertSame(SecurityScore::BAND_CRITICAL, end($bands));
        $this->assertContains('ban', [$engine->escalationFor($score)]);
    }

    public function test_scores_decay_over_time_so_accounts_can_recover(): void
    {
        $user = User::factory()->create();
        $engine = app(RiskEngine::class);

        $score = $engine->apply((string) $user->id, [
            $this->signal(SecuritySignal::WALLET_FRAUD, SecuritySeverity::CRITICAL, 90.0),
        ]);
        $before = (float) $score->risk_score;

        $score->forceFill(['last_decayed_at' => CarbonImmutable::now()->subDays(5)])->save();

        $engine->decay($score);

        $this->assertLessThan($before, (float) $score->risk_score);
        $this->assertSame(5, $score->clean_streak_days);
    }

    public function test_the_recalculate_job_applies_decay(): void
    {
        $user = User::factory()->create();
        $engine = app(RiskEngine::class);

        $score = $engine->apply((string) $user->id, [
            $this->signal(SecuritySignal::WALLET_FRAUD, SecuritySeverity::CRITICAL, 90.0),
        ]);
        $before = (float) $score->risk_score;

        $score->forceFill(['last_decayed_at' => CarbonImmutable::now()->subDays(10)])->save();

        dispatch_sync(new RecalculateRiskScoreJob((string) $user->id));

        $this->assertLessThan($before, (float) $score->fresh()->risk_score);
    }

    public function test_a_score_change_is_broadcast(): void
    {
        Event::fake([RiskScoreChanged::class]);
        $user = User::factory()->create();

        app(RiskEngine::class)->apply((string) $user->id, [
            $this->signal(SecuritySignal::BOT_PLAY, SecuritySeverity::HIGH, 88.0),
        ]);

        Event::assertDispatched(
            RiskScoreChanged::class,
            static fn (RiskScoreChanged $event): bool => $event->riskScore > $event->previousRisk,
        );
    }

    public function test_scores_never_leave_the_zero_to_one_hundred_range(): void
    {
        $user = User::factory()->create();
        $engine = app(RiskEngine::class);

        for ($i = 0; $i < 40; $i++) {
            $score = $engine->apply((string) $user->id, [
                $this->signal(SecuritySignal::WALLET_FRAUD, SecuritySeverity::CRITICAL, 100.0),
            ]);
        }

        foreach (['security_score', 'trust_score', 'risk_score', 'fraud_score', 'bot_score'] as $field) {
            $this->assertGreaterThanOrEqual(0.0, (float) $score->{$field}, $field);
            $this->assertLessThanOrEqual(100.0, (float) $score->{$field}, $field);
        }
    }

    private function signal(string $signal, string $severity, float $confidence): DetectedSignal
    {
        return DetectedSignal::make(
            signal: $signal,
            severity: $severity,
            confidence: $confidence,
            title: 'test signal',
        );
    }
}
