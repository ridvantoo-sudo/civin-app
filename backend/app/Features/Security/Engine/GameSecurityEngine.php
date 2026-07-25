<?php

namespace App\Features\Security\Engine;

use App\Features\Security\DTO\DetectedSignal;
use App\Features\Security\DTO\SecurityEventData;
use App\Features\Security\Engine\Contracts\SecurityAnalyzer;
use App\Features\Security\Models\SecurityEvent;
use App\Features\Security\Support\SecurityContext;
use App\Features\Security\Support\SecuritySeverity;
use App\Features\Security\Support\SecuritySignal;
use App\Features\Security\Support\SecuritySurface;

/**
 * Durable game-integrity checks that must survive a worker restart, where the
 * Redis-window scanners cannot help.
 */
final class GameSecurityEngine implements SecurityAnalyzer
{
    public function name(): string
    {
        return 'game';
    }

    public function supports(SecurityEventData $event): bool
    {
        return $event->surface === SecuritySurface::GAMES && $event->subjectId !== null;
    }

    public function analyze(SecurityEventData $event, SecurityContext $context): array
    {
        $settlements = SecurityEvent::query()
            ->where('surface', SecuritySurface::GAMES)
            ->where('subject_id', $event->subjectId)
            ->where('event', $event->eventName)
            ->count();

        // The current event is already persisted, so >1 means a genuine repeat.
        if ($settlements <= 1) {
            return [];
        }

        return [DetectedSignal::make(
            signal: SecuritySignal::DUPLICATE_SETTLEMENT,
            severity: SecuritySeverity::CRITICAL,
            confidence: min(96.0, 70.0 + ($settlements * 5.0)),
            title: 'Game round settled more than once',
            evidence: [
                'settlement_count' => $settlements,
                'subject_id' => $event->subjectId,
                'event' => $event->eventName,
            ],
            surface: SecuritySurface::GAMES,
            userId: $event->userId,
        )];
    }
}
