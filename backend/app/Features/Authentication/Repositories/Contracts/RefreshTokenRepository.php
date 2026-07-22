<?php

namespace App\Features\Authentication\Repositories\Contracts;

use App\Features\Authentication\Models\RefreshToken;

interface RefreshTokenRepository
{
    public function create(array $attributes): RefreshToken;

    public function findByPlainTokenForUpdate(string $plainToken): ?RefreshToken;

    public function revoke(RefreshToken $token): void;

    public function revokeFamily(string $familyId): int;

    public function revokeForDevice(string $userId, string $deviceId): int;

    public function revokeForUser(string $userId): int;
}
