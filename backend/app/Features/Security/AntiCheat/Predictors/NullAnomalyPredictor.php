<?php

namespace App\Features\Security\AntiCheat\Predictors;

use App\Features\Security\AntiCheat\Contracts\AnomalyPredictor;
use App\Features\Security\AntiCheat\Contracts\Prediction;
use App\Features\Security\DTO\SecurityEventData;

final class NullAnomalyPredictor implements AnomalyPredictor
{
    public function predictAnomaly(SecurityEventData $event, array $features = []): Prediction
    {
        return Prediction::unavailable();
    }
}
