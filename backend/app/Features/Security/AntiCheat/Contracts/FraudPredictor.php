<?php

namespace App\Features\Security\AntiCheat\Contracts;

use App\Features\Security\DTO\SecurityEventData;

/**
 * ML seam for transactional fraud. Bind a real implementation in a service
 * provider to activate it; nothing else in the platform changes.
 */
interface FraudPredictor
{
    /**
     * @param  array<string, float>  $features
     */
    public function predictFraud(SecurityEventData $event, array $features = []): Prediction;
}
