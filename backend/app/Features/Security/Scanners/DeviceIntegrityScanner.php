<?php

namespace App\Features\Security\Scanners;

use App\Features\Security\AntiCheat\Contracts\Scanner;
use App\Features\Security\DTO\DetectedSignal;
use App\Features\Security\DTO\SecurityEventData;
use App\Features\Security\Support\SecurityContext;
use App\Features\Security\Support\SecuritySeverity;
use App\Features\Security\Support\SecuritySignal;
use App\Features\Security\Support\SecuritySurface;

/**
 * Reads the device attestation carried on an event: emulators, rooted or
 * jailbroken handsets, debug/modified builds and cloned fingerprints.
 */
final class DeviceIntegrityScanner implements Scanner
{
    public function key(): string
    {
        return 'device_integrity';
    }

    public function supports(SecurityEventData $event): bool
    {
        return $event->deviceHash !== null;
    }

    public function scan(SecurityEventData $event, SecurityContext $context): array
    {
        $signals = [];

        if ($event->boolean('is_emulator')) {
            $signals[] = DetectedSignal::make(
                signal: SecuritySignal::EMULATOR,
                severity: SecuritySeverity::MEDIUM,
                confidence: 80.0,
                title: 'Emulator detected',
                evidence: [
                    'platform' => $event->platform,
                    'model' => $event->get('model'),
                ],
                surface: SecuritySurface::DEVICE,
                userId: $event->userId,
            );
        }

        $integrityFlags = array_filter([
            'rooted' => $event->boolean('is_rooted'),
            'jailbroken' => $event->boolean('is_jailbroken'),
            'debug_build' => $event->boolean('is_debug') || $event->boolean('is_debug_build'),
            'tampered_build' => $event->boolean('is_tampered') || $event->boolean('is_modified'),
        ]);

        if ($integrityFlags !== []) {
            // Each independent failure raises confidence; all four is conclusive.
            $confidence = min(96.0, 62.0 + (count($integrityFlags) * 12.0));

            $signals[] = DetectedSignal::make(
                signal: SecuritySignal::DEVICE_INTEGRITY,
                severity: SecuritySeverity::HIGH,
                confidence: $confidence,
                title: 'Device integrity compromised',
                evidence: [
                    'failed_checks' => array_keys($integrityFlags),
                    'platform' => $event->platform,
                    'app_version' => $event->get('app_version'),
                ],
                surface: SecuritySurface::DEVICE,
                userId: $event->userId,
            );
        }

        $signals = array_merge($signals, $this->detectCloning($event, $context));

        return $signals;
    }

    /**
     * A cloned device keeps the hardware fingerprint while the persistent
     * device id changes, so one hardware hash maps to several device hashes.
     *
     * @return list<DetectedSignal>
     */
    private function detectCloning(SecurityEventData $event, SecurityContext $context): array
    {
        $hardwareHash = $event->get('hardware_hash');
        if (! is_string($hardwareHash) || $hardwareHash === '') {
            return [];
        }

        $day = $event->occurredAt->format('Ymd');
        $deviceIds = $context->trackDistinct("clone:{$hardwareHash}:{$day}", $event->deviceHash, 20, 172800);

        if ($deviceIds < 3) {
            return [];
        }

        return [DetectedSignal::make(
            signal: SecuritySignal::DEVICE_CLONED,
            severity: SecuritySeverity::HIGH,
            confidence: min(93.0, 60.0 + ($deviceIds * 8.0)),
            title: 'Device cloning suspected',
            evidence: [
                'distinct_device_ids_for_hardware' => $deviceIds,
                'platform' => $event->platform,
            ],
            surface: SecuritySurface::DEVICE,
            userId: $event->userId,
        )];
    }
}
