<?php

namespace App\Features\Security\AntiCheat\Contracts;

use App\Features\Security\DTO\SecurityEventData;

/**
 * ML seam for unsupervised outlier detection across the whole event stream.
 */
interface AnomalyPredictor
{
    /**
     * @param  array<string, float>  $features
     */
    public function predictAnomaly(SecurityEventData $event, array $features = []): Prediction;
}
