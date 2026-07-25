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
 * Correlates the user ↔ device ↔ session graph to surface simultaneous
 * sessions, account sharing and one device driving many accounts.
 */
final class MultiSessionScanner implements Scanner
{
    public function key(): string
    {
        return 'multi_session';
    }

    public function supports(SecurityEventData $event): bool
    {
        return $event->isIdentified() && ($event->sessionHash !== null || $event->deviceHash !== null);
    }

    public function scan(SecurityEventData $event, SecurityContext $context): array
    {
        $thresholds = (array) config('security_platform.thresholds.sessions', []);
        $maxConcurrent = (int) ($thresholds['max_concurrent'] ?? 3);
        $maxDevicesPerUser = (int) ($thresholds['max_devices_per_user'] ?? 6);
        $maxUsersPerDevice = (int) ($thresholds['max_users_per_device'] ?? 4);

        $userId = (string) $event->userId;
        $window = $event->occurredAt->format('YmdHi');
        $signals = [];

        if ($event->sessionHash !== null) {
            $concurrent = $context->trackDistinct("sess:{$userId}:{$window}", $event->sessionHash, 20, 600);
            if ($concurrent > $maxConcurrent) {
                $signals[] = DetectedSignal::make(
                    signal: SecuritySignal::MULTIPLE_SESSIONS,
                    severity: SecuritySeverity::MEDIUM,
                    confidence: Statistics::confidenceFromRatio((float) $concurrent, (float) $maxConcurrent),
                    title: 'Multiple simultaneous sessions',
                    evidence: [
                        'concurrent_sessions' => $concurrent,
                        'limit' => $maxConcurrent,
                        'window_minute' => $window,
                    ],
                    surface: SecuritySurface::AUTH,
                    userId: $event->userId,
                );
            }
        }

        if ($event->deviceHash !== null) {
            $day = $event->occurredAt->format('Ymd');

            $devices = $context->trackDistinct("udev:{$userId}:{$day}", $event->deviceHash, 30, 172800);
            if ($devices > $maxDevicesPerUser) {
                $signals[] = DetectedSignal::make(
                    signal: SecuritySignal::ACCOUNT_SHARING,
                    severity: SecuritySeverity::MEDIUM,
                    confidence: Statistics::confidenceFromRatio((float) $devices, (float) $maxDevicesPerUser),
                    title: 'Account active on unusually many devices',
                    evidence: [
                        'distinct_devices_today' => $devices,
                        'limit' => $maxDevicesPerUser,
                    ],
                    surface: SecuritySurface::AUTH,
                    userId: $event->userId,
                );
            }

            $accountsOnDevice = $context->trackDistinct("devu:{$event->deviceHash}:{$day}", $userId, 40, 172800);
            if ($accountsOnDevice > $maxUsersPerDevice) {
                $signals[] = DetectedSignal::make(
                    signal: SecuritySignal::SMURF_ACCOUNT,
                    severity: SecuritySeverity::HIGH,
                    confidence: Statistics::confidenceFromRatio((float) $accountsOnDevice, (float) $maxUsersPerDevice),
                    title: 'Single device driving many accounts',
                    evidence: [
                        'accounts_on_device_today' => $accountsOnDevice,
                        'limit' => $maxUsersPerDevice,
                    ],
                    surface: SecuritySurface::DEVICE,
                    userId: $event->userId,
                );
            }
        }

        return $signals;
    }
}
