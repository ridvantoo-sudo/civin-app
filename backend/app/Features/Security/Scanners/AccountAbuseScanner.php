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
 * Mass account creation and smurfing, measured at registration time from the
 * device and network the signup arrived on.
 */
final class AccountAbuseScanner implements Scanner
{
    public function key(): string
    {
        return 'account_abuse';
    }

    public function supports(SecurityEventData $event): bool
    {
        return $event->surface === SecuritySurface::AUTH || $event->surface === SecuritySurface::DEVICE;
    }

    public function scan(SecurityEventData $event, SecurityContext $context): array
    {
        if (! $this->isRegistration($event)) {
            return [];
        }

        $thresholds = (array) config('security_platform.thresholds.farming', []);
        $perDevice = (int) ($thresholds['signup_per_device_per_day'] ?? 3);
        $perIp = (int) ($thresholds['signup_per_ip_per_day'] ?? 10);

        $day = $event->occurredAt->format('Ymd');
        $signals = [];

        if ($event->deviceHash !== null) {
            $count = $context->bump("reg:dev:{$event->deviceHash}:{$day}", 172800);
            if ($count > $perDevice) {
                $signals[] = DetectedSignal::make(
                    signal: SecuritySignal::MASS_ACCOUNT_CREATION,
                    severity: SecuritySeverity::HIGH,
                    confidence: Statistics::confidenceFromRatio((float) $count, (float) $perDevice),
                    title: 'Many accounts registered from one device',
                    evidence: [
                        'registrations_today' => $count,
                        'limit' => $perDevice,
                    ],
                    surface: SecuritySurface::AUTH,
                    userId: $event->userId,
                );
            }
        }

        if ($event->ipHash !== null) {
            $count = $context->bump("reg:ip:{$event->ipHash}:{$day}", 172800);
            if ($count > $perIp) {
                $signals[] = DetectedSignal::make(
                    signal: SecuritySignal::MASS_ACCOUNT_CREATION,
                    severity: SecuritySeverity::MEDIUM,
                    confidence: Statistics::confidenceFromRatio((float) $count, (float) $perIp),
                    title: 'Registration burst from one network address',
                    evidence: [
                        'registrations_today' => $count,
                        'limit' => $perIp,
                    ],
                    surface: SecuritySurface::AUTH,
                    userId: $event->userId,
                );
            }
        }

        return $signals;
    }

    private function isRegistration(SecurityEventData $event): bool
    {
        $name = strtolower($event->eventName);

        foreach (['registered', 'register', 'signup', 'sign_up', 'account_created'] as $token) {
            if (str_contains($name, $token)) {
                return true;
            }
        }

        return false;
    }
}
