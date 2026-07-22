<?php

namespace App\Features\Authentication\DTOs;

final readonly class RegisterData
{
    public function __construct(
        public string $email,
        public string $username,
        public string $password,
        public string $displayName,
        public DeviceData $device,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            $data['email'],
            $data['username'],
            $data['password'],
            $data['display_name'] ?? $data['username'],
            DeviceData::fromArray($data['device']),
        );
    }
}
