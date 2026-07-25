<?php

namespace App\Features\Security\Engine;

use App\Features\Security\DTO\DetectedSignal;
use App\Features\Security\Events\RiskScoreChanged;
use App\Features\Security\Models\SecurityScore;
use App\Features\Security\Support\SecuritySignal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Owns the five per-user scores.
 *
 * Every update runs inside a row-level transaction so concurrent queue workers
 * analyzing the same user cannot clobber each other's increments.
 */
final class RiskEngine
{
    /** Signals that additionally feed the dedicated fraud score. */
    private const FRAUD_SIGNALS = [
        SecuritySignal::WALLET_FRAUD,
        SecuritySignal::WALLET_ABUSE,
        SecuritySignal::RAPID_TRANSFERS,
        SecuritySignal::ABNORMAL_BALANCE,
        SecuritySignal::REWARD_FARMING,
        SecuritySignal::REFERRAL_ABUSE,
        SecuritySignal::DUPLICATE_SETTLEMENT,
    ];

    /** Signals that additionally feed the dedicated bot score. */
    private const BOT_SIGNALS = [
        SecuritySignal::AUTO_CLICKER,
        SecuritySignal::MACRO_BEHAVIOR,
        SecuritySignal::PERFECT_TIMING,
        SecuritySignal::IMPOSSIBLE_REACTION,
        SecuritySignal::SPEED_HACK,
        SecuritySignal::BOT_PLAY,
        SecuritySignal::API_ABUSE,
    ];

    public function profileFor(string $userId): SecurityScore
    {
        $defaults = (array) config('security_platform.scores', []);

        return SecurityScore::query()->firstOrCreate(
            ['user_id' => $userId],
            [
                'security_score' => (float) ($defaults['default_security'] ?? 80.0),
                'trust_score' => (float) ($defaults['default_trust'] ?? 70.0),
                'risk_score' => 0.0,
                'fraud_score' => 0.0,
                'bot_score' => 0.0,
                'band' => SecurityScore::BAND_LOW,
            ],
        );
    }

    /**
     * Applies a batch of signals and returns the refreshed score row.
     *
     * @param  list<DetectedSignal>  $signals
     */
    public function apply(string $userId, array $signals): SecurityScore
    {
        $this->profileFor($userId);

        [$score, $previousRisk] = DB::transaction(function () use ($userId, $signals): array {
            /** @var SecurityScore $score */
            $score = SecurityScore::query()
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            $previousRisk = (float) $score->risk_score;

            $this->decay($score);

            $breakdown = is_array($score->breakdown) ? $score->breakdown : [];

            $contributions = $this->contributions($signals);
            $riskDelta = 0.0;
            $fraudDelta = 0.0;
            $botDelta = 0.0;

            foreach ($contributions as [$signal, $points]) {
                $riskDelta += $points;

                if (in_array($signal->signal, self::FRAUD_SIGNALS, true)) {
                    $fraudDelta += $points;
                }
                if (in_array($signal->signal, self::BOT_SIGNALS, true)) {
                    $botDelta += $points;
                }

                $breakdown[$signal->signal] = round(
                    (float) ($breakdown[$signal->signal] ?? 0) + $points,
                    4,
                );
            }

            $score->risk_score = $this->clamp($score->risk_score + $riskDelta);
            $score->fraud_score = $this->clamp($score->fraud_score + $fraudDelta);
            $score->bot_score = $this->clamp($score->bot_score + $botDelta);

            // Trust and security move inversely to risk, at a gentler rate so
            // a single bad day does not destroy a long-standing reputation.
            $score->trust_score = $this->clamp($score->trust_score - ($riskDelta * 0.5));
            $score->security_score = $this->clamp(100.0 - (($score->risk_score * 0.6) + ($score->fraud_score * 0.4)));

            $score->signal_count = $score->signal_count + count($signals);
            $score->band = SecurityScore::bandFor($score->risk_score);
            $score->breakdown = array_slice($breakdown, -40, 40, true);

            if ($signals !== []) {
                $score->last_signal_at = CarbonImmutable::now();
                $score->clean_streak_days = 0;
            }

            $score->save();

            return [$score, $previousRisk];
        }, 3);

        if (abs($score->risk_score - $previousRisk) >= 0.01) {
            RiskScoreChanged::dispatch(
                $userId,
                $previousRisk,
                (float) $score->risk_score,
                (float) $score->fraud_score,
                (float) $score->bot_score,
                (float) $score->trust_score,
                (string) $score->band,
            );
        }

        return $score;
    }

    /**
     * Converts signals into risk points.
     *
     * Two dampeners keep the model honest: each signal contributes only a
     * fraction of its weighted confidence, and the total for one event is
     * capped. Several correlated detections from a single action therefore
     * cannot, on their own, escalate an account.
     *
     * @param  list<DetectedSignal>  $signals
     * @return list<array{0: DetectedSignal, 1: float}>
     */
    private function contributions(array $signals): array
    {
        $weight = (float) config('security_platform.scores.signal_weight', 0.25);
        $cap = (float) config('security_platform.scores.max_event_delta', 25.0);

        $raw = [];
        $total = 0.0;
        foreach ($signals as $signal) {
            $points = $signal->weightedScore() * $weight;
            $raw[] = [$signal, $points];
            $total += $points;
        }

        if ($cap > 0 && $total > $cap) {
            $scale = $cap / $total;
            $raw = array_map(
                static fn (array $pair): array => [$pair[0], $pair[1] * $scale],
                $raw,
            );
        }

        return $raw;
    }

    /**
     * Time-based decay so scores recover when abuse stops.
     */
    public function decay(SecurityScore $score): void
    {
        $perDay = (float) config('security_platform.scores.decay_per_day', 2.5);
        $reference = $score->last_decayed_at ?? $score->updated_at ?? $score->created_at;

        if ($perDay <= 0 || $reference === null) {
            return;
        }

        $days = CarbonImmutable::now()->diffInDays(CarbonImmutable::parse($reference), true);
        if ($days < 1) {
            return;
        }

        $reduction = $perDay * (float) floor($days);

        $score->risk_score = $this->clamp($score->risk_score - $reduction);
        $score->fraud_score = $this->clamp($score->fraud_score - $reduction);
        $score->bot_score = $this->clamp($score->bot_score - $reduction);
        $score->trust_score = $this->clamp($score->trust_score + ($reduction * 0.5));
        $score->clean_streak_days = $score->clean_streak_days + (int) floor($days);
        $score->last_decayed_at = CarbonImmutable::now();
    }

    /**
     * Does the standing score alone justify escalation, independent of any
     * single rule match?
     */
    public function escalationFor(SecurityScore $score): ?string
    {
        $limits = (array) config('security_platform.escalation', []);

        return match (true) {
            $score->risk_score >= (float) ($limits['risk_ban'] ?? 96) => 'ban',
            $score->risk_score >= (float) ($limits['risk_suspend'] ?? 88) => 'suspend',
            $score->risk_score >= (float) ($limits['risk_review'] ?? 75) => 'review',
            $score->fraud_score >= (float) ($limits['fraud_review'] ?? 80) => 'review',
            $score->bot_score >= (float) ($limits['bot_review'] ?? 85) => 'review',
            default => null,
        };
    }

    private function clamp(float $value): float
    {
        $min = (float) config('security_platform.scores.min', 0.0);
        $max = (float) config('security_platform.scores.max', 100.0);

        return round(max($min, min($max, $value)), 2);
    }
}
