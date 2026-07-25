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
 * VPN / proxy / TOR usage, IP farms and impossible travel (fake GPS).
 */
final class NetworkAbuseScanner implements Scanner
{
    public function key(): string
    {
        return 'network_abuse';
    }

    public function supports(SecurityEventData $event): bool
    {
        return $event->ipHash !== null;
    }

    public function scan(SecurityEventData $event, SecurityContext $context): array
    {
        $thresholds = (array) config('security_platform.thresholds.network', []);
        $maxUsersPerIp = (int) ($thresholds['max_users_per_ip'] ?? 8);
        $maxCountriesPerDay = (int) ($thresholds['max_countries_per_day'] ?? 3);

        $signals = [];
        $day = $event->occurredAt->format('Ymd');

        $anonymizers = array_filter([
            'vpn' => $event->boolean('is_vpn'),
            'proxy' => $event->boolean('is_proxy'),
            'tor' => $event->boolean('is_tor'),
            'hosting' => $event->boolean('is_hosting') || $event->boolean('is_datacenter'),
        ]);

        if ($anonymizers !== []) {
            $signals[] = DetectedSignal::make(
                signal: SecuritySignal::ANONYMIZER,
                // TOR is a materially stronger signal than a consumer VPN.
                severity: isset($anonymizers['tor']) ? SecuritySeverity::HIGH : SecuritySeverity::MEDIUM,
                confidence: isset($anonymizers['tor']) ? 88.0 : 70.0,
                title: 'Anonymized network path',
                evidence: [
                    'detected' => array_keys($anonymizers),
                    'country_code' => $event->countryCode,
                    'asn' => $event->get('asn'),
                ],
                surface: SecuritySurface::PLATFORM,
                userId: $event->userId,
            );
        }

        if ($event->isIdentified()) {
            $usersOnIp = $context->trackDistinct("ipu:{$event->ipHash}:{$day}", $event->userId, 60, 172800);
            if ($usersOnIp > $maxUsersPerIp) {
                $signals[] = DetectedSignal::make(
                    signal: SecuritySignal::NETWORK_ANOMALY,
                    severity: SecuritySeverity::MEDIUM,
                    confidence: Statistics::confidenceFromRatio((float) $usersOnIp, (float) $maxUsersPerIp),
                    title: 'Many accounts share one network address',
                    evidence: [
                        'accounts_on_ip_today' => $usersOnIp,
                        'limit' => $maxUsersPerIp,
                    ],
                    surface: SecuritySurface::PLATFORM,
                    userId: $event->userId,
                );
            }

            if ($event->countryCode !== null) {
                $countries = $context->trackDistinct("uctry:{$event->userId}:{$day}", $event->countryCode, 20, 172800);
                if ($countries > $maxCountriesPerDay) {
                    $signals[] = DetectedSignal::make(
                        signal: SecuritySignal::IMPOSSIBLE_TRAVEL,
                        severity: SecuritySeverity::HIGH,
                        confidence: Statistics::confidenceFromRatio((float) $countries, (float) $maxCountriesPerDay),
                        title: 'Impossible travel between countries',
                        evidence: [
                            'distinct_countries_today' => $countries,
                            'limit' => $maxCountriesPerDay,
                            'latest_country' => $event->countryCode,
                        ],
                        surface: SecuritySurface::PLATFORM,
                        userId: $event->userId,
                    );
                }
            }
        }

        if ($event->boolean('is_mock_location') || $event->boolean('is_fake_gps')) {
            $signals[] = DetectedSignal::make(
                signal: SecuritySignal::IMPOSSIBLE_TRAVEL,
                severity: SecuritySeverity::HIGH,
                confidence: 85.0,
                title: 'Mock location provider active',
                evidence: ['reported_country' => $event->countryCode],
                surface: SecuritySurface::PLATFORM,
                userId: $event->userId,
            );
        }

        return $signals;
    }
}
