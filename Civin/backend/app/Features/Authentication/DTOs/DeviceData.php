<?php

namespace App\Features\Authentication\DTOs;

final readonly class DeviceData
{
    public function __construct(
        public string $uuid,
        public string $platform,
        public string $name,
        public ?string $pushToken = null,
        public ?string $appVersion = null,
        public ?string $osVersion = null,
        public ?string $ipAddress = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            $data['device_uuid'],
            $data['platform'],
            $data['name'],
            $data['push_token'] ?? null,
            $data['app_version'] ?? null,
            $data['os_version'] ?? null,
            $data['ip_address'] ?? null,
        );
    }

    public function attributes(): array
    {
        $attributes = [
            'device_uuid' => $this->uuid,
            'platform' => $this->platform,
            'name' => $this->name,
            'push_token' => $this->pushToken,
            'app_version' => $this->appVersion,
            'os_version' => $this->osVersion,
        ];

        if ($this->ipAddress !== null) {
            $attributes['ip_address'] = $this->ipAddress;
        }

        return $attributes;
    }
}
