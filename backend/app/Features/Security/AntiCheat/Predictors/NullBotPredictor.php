<?php

namespace App\Features\Security\AntiCheat\Predictors;

use App\Features\Security\AntiCheat\Contracts\BotPredictor;
use App\Features\Security\AntiCheat\Contracts\Prediction;
use App\Features\Security\DTO\SecurityEventData;

final class NullBotPredictor implements BotPredictor
{
    public function predictBot(SecurityEventData $event, array $features = []): Prediction
    {
        return Prediction::unavailable();
    }
}
