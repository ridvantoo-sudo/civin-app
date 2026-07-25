<?php

namespace App\Features\Security\Engine;

use App\Features\Security\DTO\DetectedSignal;
use App\Features\Security\DTO\SecurityEventData;
use App\Features\Security\Engine\Contracts\SecurityAnalyzer;
use App\Features\Security\Models\SecurityDevice;
use App\Features\Security\Support\SecurityContext;
use App\Features\Security\Support\SecuritySeverity;
use App\Features\Security\Support\SecuritySignal;
use App\Features\Security\Support\SecuritySurface;
use App\Features\Security\Support\Statistics;

/**
 * Persistent device reputation.
 *
 * Where DeviceIntegrityScanner reads the attestation on a single event, this
 * engine reasons over the device's recorded history — including devices an
 * administrator has explicitly blocked.
 */
final class DeviceEngine implements SecurityAnalyzer
{
    public function name(): string
    {
        return 'device';
    }

    public function supports(SecurityEventData $event): bool
    {
        return $event->deviceHash !== null;
    }

    public function analyze(SecurityEventData $event, SecurityContext $context): array
    {
        $deviceHash = (string) $event->deviceHash;

        /** @var list<SecurityDevice> $records */
        $records = SecurityDevice::query()
            ->where('device_hash', $deviceHash)
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
                    signal: SecuritySignal::DEVICE_INTEGRITY,
                    severity: SecuritySeverity::CRITICAL,
                    confidence: 96.0,
                    title: 'Activity from an administratively blocked device',
                    evidence: [
                        'device_id' => $record->id,
                        'blocked' => true,
                    ],
                    surface: SecuritySurface::DEVICE,
                    userId: $event->userId,
                );
                break;
            }
        }

        $linkedAccounts = count(array_unique(array_filter(array_map(
            static fn (SecurityDevice $device): ?string => $device->user_id,
            $records,
        ))));

        $maxUsersPerDevice = (int) config('security_platform.thresholds.sessions.max_users_per_device', 4);
        if ($linkedAccounts > $maxUsersPerDevice) {
            $signals[] = DetectedSignal::make(
                signal: SecuritySignal::SMURF_ACCOUNT,
                severity: SecuritySeverity::HIGH,
                confidence: Statistics::confidenceFromRatio((float) $linkedAccounts, (float) $maxUsersPerDevice),
                title: 'Device historically linked to many accounts',
                evidence: [
                    'linked_accounts' => $linkedAccounts,
                    'limit' => $maxUsersPerDevice,
                ],
                surface: SecuritySurface::DEVICE,
                userId: $event->userId,
            );
        }

        foreach ($records as $record) {
            if ($record->is_cloned) {
                $signals[] = DetectedSignal::make(
                    signal: SecuritySignal::DEVICE_CLONED,
                    severity: SecuritySeverity::HIGH,
                    confidence: 82.0,
                    title: 'Device previously confirmed as cloned',
                    evidence: ['device_id' => $record->id],
                    surface: SecuritySurface::DEVICE,
                    userId: $event->userId,
                );
                break;
            }
        }

        return $signals;
    }
}
