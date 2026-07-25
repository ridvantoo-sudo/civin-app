<?php

namespace App\Features\Security\Engine;

use App\Features\Security\DTO\DetectedSignal;
use App\Features\Security\DTO\SecurityEventData;
use App\Features\Security\Engine\Contracts\SecurityAnalyzer;
use App\Features\Security\Models\SecurityFlag;
use App\Features\Security\Support\SecurityContext;
use App\Features\Security\Support\SecuritySeverity;
use App\Features\Security\Support\SecuritySignal;
use App\Features\Security\Support\SecuritySurface;

/**
 * Live and voice authenticity at the account level: hosts and agencies with a
 * standing history of manufactured engagement.
 */
final class LiveSecurityEngine implements SecurityAnalyzer
{
    public function name(): string
    {
        return 'live';
    }

    public function supports(SecurityEventData $event): bool
    {
        return $event->isIdentified() && in_array($event->surface, [
            SecuritySurface::LIVE,
            SecuritySurface::VOICE,
            SecuritySurface::RANKING,
            SecuritySurface::AGENCY,
        ], true);
    }

    public function analyze(SecurityEventData $event, SecurityContext $context): array
    {
        $flagged = SecurityFlag::query()
            ->active()
            ->where('user_id', $event->userId)
            ->whereIn('flag', [SecuritySignal::FAKE_ENGAGEMENT, SecuritySignal::ARTIFICIAL_RANKING])
            ->orderByDesc('hit_count')
            ->first();

        if ($flagged === null) {
            return [];
        }

        return [DetectedSignal::make(
            signal: SecuritySignal::ARTIFICIAL_RANKING,
            severity: SecuritySeverity::HIGH,
            confidence: min(90.0, 60.0 + ($flagged->hit_count * 4.0)),
            title: 'Host has a standing fake-engagement history',
            evidence: [
                'flag' => $flagged->flag,
                'hit_count' => $flagged->hit_count,
                'surface' => $event->surface,
            ],
            surface: $event->surface,
            userId: $event->userId,
        )];
    }
}
