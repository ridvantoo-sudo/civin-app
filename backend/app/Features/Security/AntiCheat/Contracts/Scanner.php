<?php

namespace App\Features\Security\AntiCheat\Contracts;

use App\Features\Security\DTO\DetectedSignal;
use App\Features\Security\DTO\SecurityEventData;
use App\Features\Security\Support\SecurityContext;

/**
 * A detector.
 *
 * Adding a new form of cheating means adding a class that implements this
 * interface and listing it in `config/security_platform.php`. SecurityEngine,
 * RiskEngine and SecurityRuleEngine never change — that is the Open/Closed
 * guarantee for Phase 12.
 */
interface Scanner
{
    /** Stable identifier used in logs and the admin UI. */
    public function key(): string;

    /**
     * Cheap pre-filter. Return false to skip the scan entirely; this runs for
     * every event so it must not query anything.
     */
    public function supports(SecurityEventData $event): bool;

    /**
     * @return list<DetectedSignal>
     */
    public function scan(SecurityEventData $event, SecurityContext $context): array;
}
