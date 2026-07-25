<?php

namespace App\Features\Security\Engine;

use App\Features\Security\AntiCheat\Contracts\AnomalyPredictor;
use App\Features\Security\DTO\DetectedSignal;
use App\Features\Security\DTO\SecurityEventData;
use App\Features\Security\Engine\Contracts\SecurityAnalyzer;
use App\Features\Security\Models\SecurityScore;
use App\Features\Security\Support\SecurityContext;
use App\Features\Security\Support\SecuritySeverity;
use App\Features\Security\Support\SecuritySignal;

/**
 * Composite fraud judgement.
 *
 * Runs last so it can weigh the account's standing scores against the current
 * event, and is the home of the unsupervised anomaly seam.
 */
final class FraudEngine implements SecurityAnalyzer
{
    public function name(): string
    {
        return 'fraud';
    }

    public function __construct(private readonly AnomalyPredictor $predictor) {}

    public function supports(SecurityEventData $event): bool
    {
        return $event->isIdentified();
    }

    public function analyze(SecurityEventData $event, SecurityContext $context): array
    {
        $score = SecurityScore::query()->where('user_id', $event->userId)->first();
        $signals = [];

        if ($score !== null) {
            $fraudThreshold = (float) config('security_platform.escalation.fraud_review', 80.0);

            // A high standing fraud score plus real money moving is actionable
            // even when the individual transaction looks ordinary.
            if ($score->fraud_score >= $fraudThreshold && $event->amountAsFloat() > 0) {
                $signals[] = DetectedSignal::make(
                    signal: SecuritySignal::WALLET_FRAUD,
                    severity: SecuritySeverity::HIGH,
                    confidence: min(95.0, $score->fraud_score),
                    title: 'Value movement on a high fraud-score account',
                    evidence: [
                        'fraud_score' => $score->fraud_score,
                        'risk_score' => $score->risk_score,
                        'amount' => $event->amount,
                        'surface' => $event->surface,
                    ],
                    surface: $event->surface,
                    userId: $event->userId,
                );
            }
        }

        $prediction = $this->predictor->predictAnomaly($event, [
            'amount' => $event->amountAsFloat(),
            'risk_score' => (float) ($score->risk_score ?? 0),
            'fraud_score' => (float) ($score->fraud_score ?? 0),
            'bot_score' => (float) ($score->bot_score ?? 0),
        ]);

        if ($prediction->available && $prediction->score >= 0.9) {
            $signals[] = DetectedSignal::make(
                signal: SecuritySignal::NETWORK_ANOMALY,
                severity: SecuritySeverity::HIGH,
                confidence: $prediction->asConfidence(),
                title: 'Event is a statistical outlier',
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
}
