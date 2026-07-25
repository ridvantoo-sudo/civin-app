<?php

namespace App\Features\Security\Engine;

use App\Features\Security\AntiCheat\Contracts\BehaviorPredictor;
use App\Features\Security\DTO\DetectedSignal;
use App\Features\Security\DTO\SecurityEventData;
use App\Features\Security\Engine\Contracts\SecurityAnalyzer;
use App\Features\Security\Models\SecurityFlag;
use App\Features\Security\Support\SecurityContext;
use App\Features\Security\Support\SecuritySeverity;
use App\Features\Security\Support\SecuritySignal;

/**
 * Repeat-offender escalation and behavioural drift.
 *
 * A user who keeps re-triggering the same flag becomes progressively more
 * suspicious even when no individual event is conclusive.
 */
final class BehaviorEngine implements SecurityAnalyzer
{
    private const REPEAT_THRESHOLD = 5;

    public function __construct(private readonly BehaviorPredictor $predictor) {}

    public function name(): string
    {
        return 'behavior';
    }

    public function supports(SecurityEventData $event): bool
    {
        return $event->isIdentified();
    }

    public function analyze(SecurityEventData $event, SecurityContext $context): array
    {
        $signals = [];

        /** @var list<SecurityFlag> $flags */
        $flags = SecurityFlag::query()
            ->active()
            ->where('user_id', $event->userId)
            ->orderByDesc('hit_count')
            ->limit(10)
            ->get()
            ->all();

        foreach ($flags as $flag) {
            if ($flag->hit_count < self::REPEAT_THRESHOLD) {
                continue;
            }

            $signals[] = DetectedSignal::make(
                signal: $flag->flag,
                severity: $this->escalate($flag->severity),
                // Confidence grows with recidivism but never fabricates certainty.
                confidence: min(92.0, 55.0 + ($flag->hit_count * 3.0)),
                title: 'Repeat offender: '.$flag->flag,
                evidence: [
                    'hit_count' => $flag->hit_count,
                    'first_flagged_at' => $flag->first_flagged_at?->toIso8601String(),
                    'last_flagged_at' => $flag->last_flagged_at?->toIso8601String(),
                ],
                surface: $flag->surface ?? $event->surface,
                userId: $event->userId,
            );
        }

        $prediction = $this->predictor->predictBehavior($event, [
            'active_flags' => (float) count($flags),
        ]);

        if ($prediction->available && $prediction->score >= 0.85) {
            $signals[] = DetectedSignal::make(
                signal: SecuritySignal::BOT_PLAY,
                severity: SecuritySeverity::HIGH,
                confidence: $prediction->asConfidence(),
                title: 'Behaviour deviates sharply from user baseline',
                evidence: [
                    'model_score' => round($prediction->score, 4),
                    'explanation' => $prediction->explanation,
                ],
                surface: $event->surface,
                userId: $event->userId,
            );
        }

        return $signals;
    }

    private function escalate(string $severity): string
    {
        return match ($severity) {
            SecuritySeverity::INFO => SecuritySeverity::LOW,
            SecuritySeverity::LOW => SecuritySeverity::MEDIUM,
            SecuritySeverity::MEDIUM => SecuritySeverity::HIGH,
            default => SecuritySeverity::CRITICAL,
        };
    }
}
