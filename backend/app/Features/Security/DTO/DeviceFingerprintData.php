<?php

namespace App\Features\Security\DTO;

use App\Features\Security\Support\Fingerprint;

/**
 * Client-reported device attestation, already reduced to hashes.
 */
final readonly class DeviceFingerprintData
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $deviceHash,
        public ?string $hardwareHash,
        public ?string $osHash,
        public ?string $appHash,
        public ?string $platform,
        public ?string $osVersion,
        public ?string $appVersion,
        public ?string $model,
        public bool $isEmulator = false,
        public bool $isRooted = false,
        public bool $isJailbroken = false,
        public bool $isDebugBuild = false,
        public bool $isTampered = false,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromRequest(array $input): self
    {
        $deviceId = (string) ($input['device_id'] ?? $input['device_uuid'] ?? '');

        return new self(
            deviceHash: Fingerprint::device($deviceId) ?? Fingerprint::composite($input, 'device') ?? Fingerprint::device('unknown'),
            hardwareHash: Fingerprint::composite([
                'model' => $input['model'] ?? null,
                'manufacturer' => $input['manufacturer'] ?? null,
                'cores' => $input['cpu_cores'] ?? null,
                'memory' => $input['memory_mb'] ?? null,
                'screen' => $input['screen'] ?? null,
            ], 'hardware'),
            osHash: Fingerprint::composite([
                'platform' => $input['platform'] ?? null,
                'os_version' => $input['os_version'] ?? null,
                'locale' => $input['locale'] ?? null,
                'timezone' => $input['timezone'] ?? null,
            ], 'os'),
            appHash: Fingerprint::composite([
                'app_version' => $input['app_version'] ?? null,
                'build_number' => $input['build_number'] ?? null,
                'package' => $input['package_name'] ?? null,
                'signature' => $input['build_signature'] ?? null,
            ], 'app'),
            platform: self::text($input['platform'] ?? null, 32),
            osVersion: self::text($input['os_version'] ?? null, 64),
            appVersion: self::text($input['app_version'] ?? null, 32),
            model: self::text($input['model'] ?? null, 96),
            isEmulator: self::flag($input['is_emulator'] ?? null),
            isRooted: self::flag($input['is_rooted'] ?? null),
            isJailbroken: self::flag($input['is_jailbroken'] ?? null),
            isDebugBuild: self::flag($input['is_debug'] ?? $input['is_debug_build'] ?? null),
            isTampered: self::flag($input['is_tampered'] ?? $input['is_modified'] ?? null),
            raw: self::scrub($input),
        );
    }

    public function isCompromised(): bool
    {
        return $this->isRooted || $this->isJailbroken || $this->isTampered || $this->isDebugBuild;
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'device_hash' => $this->deviceHash,
            'hardware_hash' => $this->hardwareHash,
            'os_hash' => $this->osHash,
            'app_hash' => $this->appHash,
            'platform' => $this->platform,
            'os_version' => $this->osVersion,
            'app_version' => $this->appVersion,
            'model' => $this->model,
            'is_emulator' => $this->isEmulator,
            'is_rooted' => $this->isRooted,
            'is_jailbroken' => $this->isJailbroken,
            'is_debug_build' => $this->isDebugBuild,
            'is_tampered' => $this->isTampered,
        ];
    }

    private static function text(mixed $value, int $length): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $length);
    }

    private static function flag(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private static function scrub(array $input): array
    {
        $blocked = array_map('strtolower', (array) config('security_platform.redacted_payload_keys', []));
        $out = [];
        foreach ($input as $key => $value) {
            if (in_array(strtolower((string) $key), $blocked, true)) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $out[(string) $key] = $value;
            }
        }

        return $out;
    }
}
