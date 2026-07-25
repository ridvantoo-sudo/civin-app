<?php

namespace App\Features\Ai;

use App\Features\Ai\Contracts\AiProvider;
use App\Features\Ai\Providers\FakeAiProvider;
use App\Features\Ai\Services\AiService;
use Illuminate\Support\ServiceProvider;

/**
 * AI stubs: text/voice moderation, chat translation, ML-style recommendations,
 * and highlight suggestions. Uses FakeAiProvider by default — no external LLM.
 * Does not replace Security ModerationEngine/FraudEngine or Discovery scorers.
 */
final class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/ai.php', 'ai');

        $this->app->singleton(AiProvider::class, function (): AiProvider {
            return match ((string) config('ai.driver', 'fake')) {
                default => new FakeAiProvider,
            };
        });

        $this->app->singleton(AiService::class);
    }
}
