<?php

namespace App\Features\Authentication\DTOs;

final readonly class LoginData
{
    public function __construct(
        public string $login,
        public string $password,
        public DeviceData $device,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self($data['login'], $data['password'], DeviceData::fromArray($data['device']));
    }
}
