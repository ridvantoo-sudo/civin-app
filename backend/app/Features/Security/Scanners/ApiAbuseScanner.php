<?php

namespace App\Features\Security\Scanners;

use App\Features\Security\AntiCheat\Contracts\Scanner;
use App\Features\Security\DTO\DetectedSignal;
use App\Features\Security\DTO\SecurityEventData;
use App\Features\Security\Support\SecurityContext;
use App\Features\Security\Support\SecuritySeverity;
use App\Features\Security\Support\SecuritySignal;
use App\Features\Security\Support\SecuritySurface;
use App\Features\Security\Support\Statistics;

/**
 * Volumetric API abuse and packet flooding, measured per identity and per IP.
 *
 * This complements — and never replaces — the per-route `throttle` middleware
 * the existing modules already apply.
 */
final class ApiAbuseScanner implements Scanner
{
    private const PER_MINUTE_LIMIT = 600;

    private const IP_PER_MINUTE_LIMIT = 1200;

    public function key(): string
    {
        return 'api_abuse';
    }

    public function supports(SecurityEventData $event): bool
    {
        return true;
    }

    public function scan(SecurityEventData $event, SecurityContext $context): array
    {
        $minute = $event->occurredAt->format('YmdHi');
        $actor = $context->actorKey();
        $signals = [];

        $actorRate = $context->bump("api:actor:{$actor}:{$minute}", 180);
        if ($actorRate > self::PER_MINUTE_LIMIT) {
            $signals[] = DetectedSignal::make(
                signal: SecuritySignal::API_ABUSE,
                severity: SecuritySeverity::MEDIUM,
                confidence: Statistics::confidenceFromRatio((float) $actorRate, (float) self::PER_MINUTE_LIMIT),
                title: 'Excessive request volume for one identity',
                evidence: [
                    'events_this_minute' => $actorRate,
                    'limit' => self::PER_MINUTE_LIMIT,
                ],
                surface: SecuritySurface::PLATFORM,
                userId: $event->userId,
            );
        }

        if ($event->ipHash !== null) {
            $ipRate = $context->bump("api:ip:{$event->ipHash}:{$minute}", 180);
            if ($ipRate > self::IP_PER_MINUTE_LIMIT) {
                $signals[] = DetectedSignal::make(
                    signal: SecuritySignal::API_ABUSE,
                    severity: SecuritySeverity::HIGH,
                    confidence: Statistics::confidenceFromRatio((float) $ipRate, (float) self::IP_PER_MINUTE_LIMIT),
                    title: 'Packet flood from single network address',
                    evidence: [
                        'events_this_minute' => $ipRate,
                        'limit' => self::IP_PER_MINUTE_LIMIT,
                    ],
                    surface: SecuritySurface::PLATFORM,
                    userId: $event->userId,
                );
            }
        }

        return $signals;
    }
}
