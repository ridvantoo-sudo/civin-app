<?php

namespace App\Features\Security\AntiCheat\Contracts;

use App\Features\Security\DTO\SecurityEventData;

/**
 * ML seam for "is this a human?" classification.
 */
interface BotPredictor
{
    /**
     * @param  array<string, float>  $features
     */
    public function predictBot(SecurityEventData $event, array $features = []): Prediction;
}
