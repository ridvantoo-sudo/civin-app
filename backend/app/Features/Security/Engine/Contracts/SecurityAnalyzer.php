<?php

namespace App\Features\Security\Engine\Contracts;

use App\Features\Security\DTO\DetectedSignal;
use App\Features\Security\DTO\SecurityEventData;
use App\Features\Security\Support\SecurityContext;

/**
 * A domain engine.
 *
 * Where a Scanner is a stateless streaming detector backed by Redis windows,
 * an analyzer reasons over persisted security state (devices, networks, flags,
 * bans) for one platform surface.
 *
 * Both are registered in config, so the orchestrator never changes.
 */
interface SecurityAnalyzer
{
    public function name(): string;

    public function supports(SecurityEventData $event): bool;

    /**
     * @return list<DetectedSignal>
     */
    public function analyze(SecurityEventData $event, SecurityContext $context): array;
}
