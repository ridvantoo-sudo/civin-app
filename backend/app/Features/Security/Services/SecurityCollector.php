<?php

namespace App\Features\Security\Services;

use App\Features\Security\DTO\SecurityEventData;
use App\Features\Security\Engine\SecurityEngine;
use App\Features\Security\Jobs\AnalyzeSecurityEventJob;
use App\Features\Security\Repositories\Contracts\SecurityRepository;
use App\Features\Security\Support\SecurityEventNormalizer;

/**
 * Ingestion entrypoint.
 *
 * The only work done on the source request path is normalization and a queue
 * push. Everything expensive happens in AnalyzeSecurityEventJob, so gameplay
 * latency is unaffected no matter how many detectors are registered.
 */
final class SecurityCollector
{
    public function __construct(
        private readonly SecurityEventNormalizer $normalizer,
        private readonly SecurityRepository $repository,
        private readonly SecurityEngine $engine,
    ) {}

    /**
     * @param  array<int, mixed>  $payload
     */
    public function collectFromDomainEvent(string $eventName, array $payload = []): void
    {
        if (! (bool) config('security_platform.enabled', true)) {
            return;
        }

        $normalized = $this->normalizer->normalize($eventName, $payload);
        if ($normalized === null) {
            return;
        }

        $this->queue($normalized);
    }

    public function queue(SecurityEventData $event): void
    {
        // Queue outages must never break Filament/API requests.
        try {
            AnalyzeSecurityEventJob::dispatch($event)
                ->onConnection((string) config('security_platform.queue_connection', 'database'))
                ->onQueue((string) config('security_platform.queue', 'security'));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Persist then analyze. Runs inside the queue worker.
     */
    public function process(SecurityEventData $event): void
    {
        $stored = $this->repository->appendEvent($event);
        if ($stored === null) {
            // Duplicate delivery — the original was already analyzed.
            return;
        }

        $verdict = $this->engine->analyze($event, (string) $stored->id);

        $this->repository->markAnalyzed((string) $stored->id, $verdict->riskDelta);
    }
}
