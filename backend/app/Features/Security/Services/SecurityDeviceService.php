<?php

namespace App\Features\Security\Services;

use App\Features\Security\DTO\DeviceFingerprintData;
use App\Features\Security\Models\SecurityDevice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Owns the `security_devices` table. Deliberately separate from the existing
 * Devices feature, which keeps its own `devices` table untouched.
 */
final class SecurityDeviceService
{
    public function __construct(private readonly SecurityAuditLogger $logger) {}

    /**
     * Records an attestation, preserving prior fingerprints as history.
     */
    public function register(?string $userId, DeviceFingerprintData $fingerprint): SecurityDevice
    {
        return DB::transaction(function () use ($userId, $fingerprint): SecurityDevice {
            /** @var SecurityDevice $device */
            $device = SecurityDevice::query()->firstOrCreate(
                ['user_id' => $userId, 'device_hash' => $fingerprint->deviceHash],
                array_merge($fingerprint->toAttributes(), [
                    'user_id' => $userId,
                    'seen_count' => 0,
                    'is_cloned' => false,
                    'is_trusted' => false,
                    'is_blocked' => false,
                    'risk_score' => 0.0,
                    'linked_account_count' => 0,
                    'first_seen_at' => CarbonImmutable::now(),
                    'fingerprint' => $fingerprint->raw,
                    'history' => [],
                ]),
            );

            $history = is_array($device->history) ? $device->history : [];
            if ($device->hardware_hash !== null && $device->hardware_hash !== $fingerprint->hardwareHash) {
                // Hardware changing under a stable device id is worth keeping.
                $history[] = [
                    'hardware_hash' => $device->hardware_hash,
                    'os_hash' => $device->os_hash,
                    'app_hash' => $device->app_hash,
                    'observed_at' => CarbonImmutable::now()->toIso8601String(),
                ];
                $history = array_slice($history, -20);
            }

            $device->fill($fingerprint->toAttributes());
            $device->fingerprint = $fingerprint->raw;
            $device->history = $history;
            $device->seen_count = $device->seen_count + 1;
            $device->last_seen_at = CarbonImmutable::now();
            $device->risk_score = $this->scoreFor($fingerprint);
            $device->linked_account_count = $this->linkedAccounts($fingerprint->deviceHash);
            $device->is_cloned = $this->looksCloned($fingerprint);
            $device->save();

            return $device;
        }, 3);
    }

    /**
     * @return Collection<int, SecurityDevice>
     */
    public function forUser(string $userId): Collection
    {
        return SecurityDevice::query()
            ->where('user_id', $userId)
            ->orderByDesc('last_seen_at')
            ->limit(50)
            ->get();
    }

    public function trust(SecurityDevice $device, ?string $actorId): SecurityDevice
    {
        $device->is_trusted = true;
        $device->is_blocked = false;
        $device->save();

        $this->logger->admin(
            'device.trusted',
            'Device marked trusted',
            $actorId,
            $device->user_id,
            ['device_id' => $device->id],
        );

        return $device;
    }

    public function block(SecurityDevice $device, ?string $actorId, string $reason = ''): SecurityDevice
    {
        $device->is_blocked = true;
        $device->is_trusted = false;
        $device->save();

        $this->logger->admin(
            'device.blocked',
            $reason !== '' ? $reason : 'Device blocked',
            $actorId,
            $device->user_id,
            ['device_id' => $device->id],
        );

        return $device;
    }

    private function scoreFor(DeviceFingerprintData $fingerprint): float
    {
        $score = 0.0;
        $score += $fingerprint->isEmulator ? 25.0 : 0.0;
        $score += $fingerprint->isRooted ? 30.0 : 0.0;
        $score += $fingerprint->isJailbroken ? 30.0 : 0.0;
        $score += $fingerprint->isTampered ? 35.0 : 0.0;
        $score += $fingerprint->isDebugBuild ? 15.0 : 0.0;

        return round(min(100.0, $score), 2);
    }

    private function linkedAccounts(string $deviceHash): int
    {
        return (int) SecurityDevice::query()
            ->where('device_hash', $deviceHash)
            ->whereNotNull('user_id')
            ->distinct()
            ->count('user_id');
    }

    /**
     * The same hardware reporting several persistent device ids is the
     * signature of a cloning app.
     */
    private function looksCloned(DeviceFingerprintData $fingerprint): bool
    {
        if ($fingerprint->hardwareHash === null) {
            return false;
        }

        $distinctDeviceHashes = (int) SecurityDevice::query()
            ->where('hardware_hash', $fingerprint->hardwareHash)
            ->distinct()
            ->count('device_hash');

        return $distinctDeviceHashes >= 3;
    }
}
