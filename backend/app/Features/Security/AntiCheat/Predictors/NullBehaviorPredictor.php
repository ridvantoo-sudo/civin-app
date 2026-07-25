<?php

namespace App\Features\Security\AntiCheat\Predictors;

use App\Features\Security\AntiCheat\Contracts\BehaviorPredictor;
use App\Features\Security\AntiCheat\Contracts\Prediction;
use App\Features\Security\DTO\SecurityEventData;

final class NullBehaviorPredictor implements BehaviorPredictor
{
    public function predictBehavior(SecurityEventData $event, array $features = []): Prediction
    {
        return Prediction::unavailable();
    }
}
