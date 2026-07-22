<?php

namespace App\Features\Authentication\Repositories\Eloquent;

use App\Features\Authentication\Models\RefreshToken;
use App\Features\Authentication\Repositories\Contracts\RefreshTokenRepository;

final class EloquentRefreshTokenRepository implements RefreshTokenRepository
{
    public function create(array $attributes): RefreshToken
    {
        return RefreshToken::query()->create($attributes);
    }

    public function findByPlainTokenForUpdate(string $plainToken): ?RefreshToken
    {
        return RefreshToken::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->lockForUpdate()
            ->first();
    }

    public function revoke(RefreshToken $token): void
    {
        $token->update(['revoked_at' => now(), 'last_used_at' => now()]);
    }

    public function revokeFamily(string $familyId): int
    {
        return RefreshToken::query()
            ->where('family_id', $familyId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function revokeForDevice(string $userId, string $deviceId): int
    {
        return RefreshToken::query()
            ->where('user_id', $userId)
            ->where('device_id', $deviceId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function revokeForUser(string $userId): int
    {
        return RefreshToken::query()
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}
