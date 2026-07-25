<?php

namespace App\Features\Security\AntiCheat\Predictors;

use App\Features\Security\AntiCheat\Contracts\FraudPredictor;
use App\Features\Security\AntiCheat\Contracts\Prediction;
use App\Features\Security\DTO\SecurityEventData;

/**
 * Default adapter: reports "no opinion" so the platform ships without ML.
 * Replace the binding in config to plug a model in.
 */
final class NullFraudPredictor implements FraudPredictor
{
    public function predictFraud(SecurityEventData $event, array $features = []): Prediction
    {
        return Prediction::unavailable();
    }
}
