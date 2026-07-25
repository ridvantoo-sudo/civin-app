<?php

namespace App\Features\Security\Engine;

use App\Features\Security\DTO\DetectedSignal;
use App\Features\Security\DTO\SecurityEventData;
use App\Features\Security\Engine\Contracts\SecurityAnalyzer;
use App\Features\Security\Models\SecurityNetwork;
use App\Features\Security\Support\SecurityContext;
use App\Features\Security\Support\SecuritySeverity;
use App\Features\Security\Support\SecuritySignal;
use App\Features\Security\Support\SecuritySurface;
use App\Features\Security\Support\Statistics;

/**
 * Persistent network reputation: blocked ranges, known anonymizers and IPs
 * that have historically hosted many distinct accounts.
 */
final class NetworkEngine implements SecurityAnalyzer
{
    public function name(): string
    {
        return 'network';
    }

    public function supports(SecurityEventData $event): bool
    {
        return $event->ipHash !== null;
    }

    public function analyze(SecurityEventData $event, SecurityContext $context): array
    {
        $ipHash = (string) $event->ipHash;

        /** @var list<SecurityNetwork> $records */
        $records = SecurityNetwork::query()
            ->where('ip_hash', $ipHash)
            ->limit(25)
            ->get()
            ->all();

        if ($records === []) {
            return [];
        }

        $signals = [];

        foreach ($records as $record) {
            if ($record->is_blocked) {
                $signals[] = DetectedSignal::make(
                    signal: SecuritySignal::NETWORK_ANOMALY,
                    severity: SecuritySeverity::CRITICAL,
                    confidence: 95.0,
                    title: 'Traffic from an administratively blocked network',
                    evidence: [
                        'asn' => $record->asn,
                        'country_code' => $record->country_code,
                    ],
                    surface: SecuritySurface::PLATFORM,
                    userId: $event->userId,
                );
                break;
            }
        }

        foreach ($records as $record) {
            if ($record->isAnonymized()) {
                $signals[] = DetectedSignal::make(
                    signal: SecuritySignal::ANONYMIZER,
                    severity: $record->is_tor ? SecuritySeverity::HIGH : SecuritySeverity::MEDIUM,
                    confidence: $record->is_tor ? 88.0 : 70.0,
                    title: 'Network previously classified as anonymized',
                    evidence: [
                        'vpn' => $record->is_vpn,
                        'proxy' => $record->is_proxy,
                        'tor' => $record->is_tor,
                        'asn' => $record->asn,
                    ],
                    surface: SecuritySurface::PLATFORM,
                    userId: $event->userId,
                );
                break;
            }
        }

        $distinctUsers = count(array_unique(array_filter(array_map(
            static fn (SecurityNetwork $network): ?string => $network->user_id,
            $records,
        ))));

        $limit = (int) config('security_platform.thresholds.network.max_users_per_ip', 8);
        if ($distinctUsers > $limit) {
            $signals[] = DetectedSignal::make(
                signal: SecuritySignal::NETWORK_ANOMALY,
                severity: SecuritySeverity::MEDIUM,
                confidence: Statistics::confidenceFromRatio((float) $distinctUsers, (float) $limit),
                title: 'Network historically shared by many accounts',
                evidence: [
                    'distinct_accounts' => $distinctUsers,
                    'limit' => $limit,
                ],
                surface: SecuritySurface::PLATFORM,
                userId: $event->userId,
            );
        }

        return $signals;
    }
}
