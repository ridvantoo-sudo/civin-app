<?php

namespace App\Features\Security\AntiCheat\Contracts;

use App\Features\Security\DTO\SecurityEventData;

/**
 * ML seam for behavioural deviation from a user's own baseline.
 */
interface BehaviorPredictor
{
    /**
     * @param  array<string, float>  $features
     */
    public function predictBehavior(SecurityEventData $event, array $features = []): Prediction;
}
