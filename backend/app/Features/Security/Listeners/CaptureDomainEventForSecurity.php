<?php

namespace App\Features\Security\Listeners;

use App\Features\Security\Services\SecurityCollector;
use Throwable;

/**
 * The single integration point with the rest of Civin.
 *
 * Registered as a wildcard listener, it observes every domain event without
 * any module knowing the security platform exists. It normalizes and enqueues,
 * nothing more — and it can never throw into a caller's request.
 */
final class CaptureDomainEventForSecurity
{
    public function __construct(private readonly SecurityCollector $collector) {}

    /**
     * @param  array<int, mixed>  $payload
     */
    public function handle(string $eventName, array $payload = []): void
    {
        try {
            $this->collector->collectFromDomainEvent($eventName, $payload);
        } catch (Throwable) {
            // Observation must never break the module being observed.
        }
    }
}
