<?php

namespace App\Features\Security\Actions;

use App\Features\Security\DTO\DeviceFingerprintData;
use App\Features\Security\Services\SecurityCollector;
use App\Features\Security\Services\SecurityDeviceService;
use App\Features\Security\Services\SecurityNetworkService;

/**
 * Client device attestation. The response tells the app whether the engine
 * considers the device usable; it never changes what any module will do.
 */
final readonly class VerifyDevice
{
    public function __construct(
        private SecurityDeviceService $devices,
        private SecurityNetworkService $networks,
        private SecurityCollector $collector,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function execute(string $userId, array $input, ?string $ipAddress = null): array
    {
        $fingerprint = DeviceFingerprintData::fromRequest($input);
        $device = $this->devices->register($userId, $fingerprint);

        $this->networks->record($userId, $ipAddress, [
            'country_code' => $input['country_code'] ?? null,
            'region' => $input['region'] ?? null,
            'is_vpn' => $input['is_vpn'] ?? null,
            'is_proxy' => $input['is_proxy'] ?? null,
            'is_tor' => $input['is_tor'] ?? null,
            'latency_ms' => $input['latency_ms'] ?? null,
        ]);

        $this->collector->collectFromDomainEvent('security.client.device_verified', [[
            'user_id' => $userId,
            'device_id' => $input['device_id'] ?? $input['device_uuid'] ?? null,
            'platform' => $fingerprint->platform,
            'is_emulator' => $fingerprint->isEmulator,
            'is_rooted' => $fingerprint->isRooted,
            'is_jailbroken' => $fingerprint->isJailbroken,
            'is_debug' => $fingerprint->isDebugBuild,
            'is_tampered' => $fingerprint->isTampered,
            'hardware_hash' => $fingerprint->hardwareHash,
        ]]);

        return [
            'device_id' => $device->id,
            'verified' => ! $device->is_blocked,
            'trusted' => (bool) $device->is_trusted,
            'blocked' => (bool) $device->is_blocked,
            'cloned' => (bool) $device->is_cloned,
            'integrity_compromised' => $fingerprint->isCompromised(),
            'risk_score' => (float) $device->risk_score,
            'linked_account_count' => $device->linked_account_count,
        ];
    }
}
