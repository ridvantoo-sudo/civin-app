<?php

namespace App\Features\Security\Scanners;

use App\Features\Security\AntiCheat\Contracts\BotPredictor;
use App\Features\Security\AntiCheat\Contracts\Prediction;
use App\Features\Security\AntiCheat\Contracts\Scanner;
use App\Features\Security\DTO\DetectedSignal;
use App\Features\Security\DTO\SecurityEventData;
use App\Features\Security\Support\SecurityContext;
use App\Features\Security\Support\SecuritySeverity;
use App\Features\Security\Support\SecuritySignal;
use App\Features\Security\Support\Statistics;

/**
 * Session-shape analysis for bot play: unbroken activity, no idle gaps and a
 * vanishingly small set of distinct actions.
 *
 * This is also the scanner that consults the optional BotPredictor, so an ML
 * model can raise or corroborate the same signal without new plumbing.
 */
final class BotBehaviorScanner implements Scanner
{
    public function __construct(private readonly BotPredictor $predictor) {}

    public function key(): string
    {
        return 'bot_behavior';
    }

    public function supports(SecurityEventData $event): bool
    {
        return $event->isIdentified();
    }

    public function scan(SecurityEventData $event, SecurityContext $context): array
    {
        $actor = $context->actorKey();
        $hourBucket = $event->occurredAt->format('YmdH');

        $actionsThisHour = $context->bump("bot:actions:{$actor}:{$hourBucket}", 7200);
        $distinctActions = $context->trackDistinct("bot:variety:{$actor}:{$hourBucket}", $event->eventName, 40, 7200);
        $activeHours = $context->trackDistinct("bot:hours:{$actor}:".$event->occurredAt->format('Ymd'), $hourBucket, 24, 172800);

        $signals = [];

        // High volume + almost no behavioural variety is the classic bot shape.
        if ($actionsThisHour >= 240 && $distinctActions <= 2) {
            $signals[] = DetectedSignal::make(
                signal: SecuritySignal::BOT_PLAY,
                severity: SecuritySeverity::HIGH,
                confidence: min(94.0, 65.0 + ($actionsThisHour / 40)),
                title: 'High-volume, low-variety activity',
                evidence: [
                    'actions_this_hour' => $actionsThisHour,
                    'distinct_event_types' => $distinctActions,
                    'active_hours_today' => $activeHours,
                ],
                surface: $event->surface,
                userId: $event->userId,
            );
        }

        // Humans sleep. 20+ distinct active hours in a day does not happen.
        if ($activeHours >= 20) {
            $signals[] = DetectedSignal::make(
                signal: SecuritySignal::BOT_PLAY,
                severity: SecuritySeverity::MEDIUM,
                confidence: Statistics::confidenceFromRatio((float) $activeHours, 19.0),
                title: 'Continuous 24h activity without idle window',
                evidence: [
                    'active_hours_today' => $activeHours,
                    'actions_this_hour' => $actionsThisHour,
                ],
                surface: $event->surface,
                userId: $event->userId,
            );
        }

        $prediction = $this->predict($event, [
            'actions_per_hour' => (float) $actionsThisHour,
            'distinct_actions' => (float) $distinctActions,
            'active_hours' => (float) $activeHours,
        ]);

        if ($prediction->available && $prediction->score >= 0.8) {
            $signals[] = DetectedSignal::make(
                signal: SecuritySignal::BOT_PLAY,
                severity: SecuritySeverity::HIGH,
                confidence: $prediction->asConfidence(),
                title: 'Model classified session as automated',
                evidence: [
                    'model_score' => round($prediction->score, 4),
                    'model_confidence' => round($prediction->confidence, 4),
                    'label' => $prediction->label,
                    'explanation' => $prediction->explanation,
                ],
                surface: $event->surface,
                userId: $event->userId,
            );
        }

        return $signals;
    }

    /**
     * @param  array<string, float>  $features
     */
    private function predict(SecurityEventData $event, array $features): Prediction
    {
        return $this->predictor->predictBot($event, $features);
    }
}
