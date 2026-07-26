<?php

namespace App\Features\GameAnalytics\Listeners;

use App\Features\GameAnalytics\Services\AnalyticsCollector;
use Throwable;

/**
 * Sync, assumption-light wildcard hook. Only normalizes then queues persistence
 * onto the analytics queue — never aggregates on the source request path.
 */
final class CollectDomainEventForAnalytics
{
    public function __construct(private readonly AnalyticsCollector $collector) {}

    /**
     * @param  array<int, mixed>  $payload
     */
    public function handle(string $eventName, array $payload = []): void
    {
        try {
            $this->collector->collectFromDomainEvent($eventName, $payload);
        } catch (Throwable) {
            // Observation must never break Filament/API requests.
        }
    }
}
