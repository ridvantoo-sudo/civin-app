<?php

namespace App\Features\Authentication\DTOs;

use App\Features\Devices\Models\Device;
use App\Features\Users\Models\User;
use DateTimeInterface;

final readonly class TokenPair
{
    public function __construct(
        public User $user,
        public Device $device,
        public string $accessToken,
        public string $refreshToken,
        public DateTimeInterface $accessExpiresAt,
        public DateTimeInterface $refreshExpiresAt,
    ) {}
}
