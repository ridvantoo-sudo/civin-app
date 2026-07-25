<?php

namespace App\Features\Games\Plugins\RedEnvelope\Repositories\Contracts;

use App\Features\Games\Models\RedEnvelope;
use App\Features\Games\Models\RedEnvelopeClaim;
use App\Features\Games\Plugins\RedEnvelope\Models\RedEnvelopeConfig;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface RedEnvelopeRepository
{
    public function activeConfig(): ?RedEnvelopeConfig;

    public function findEnvelope(string $envelopeId): ?RedEnvelope;

    public function findByIdempotency(User $creator, string $idempotencyKey): ?RedEnvelope;

    public function findClaimByIdempotency(User $user, string $idempotencyKey): ?RedEnvelopeClaim;

    public function findClaimFor(RedEnvelope $envelope, User $user): ?RedEnvelopeClaim;

    /** @param  array<string, mixed>  $attributes */
    public function createEnvelope(array $attributes): RedEnvelope;

    /** @param  array<string, mixed>  $attributes */
    public function createClaim(array $attributes): RedEnvelopeClaim;

    public function historyCreated(User $user, int $perPage = 30): LengthAwarePaginator;

    public function historyClaims(User $user, int $perPage = 30): LengthAwarePaginator;

    public function history(User $user, string $type = 'all', int $perPage = 30): LengthAwarePaginator;

    /** @return Collection<int, array<string, mixed>> */
    public function leaderboard(string $period = 'daily', string $type = 'winners', int $limit = 50): Collection;

    /** @return Collection<int, RedEnvelope> */
    public function openEnvelopesForRoom(?string $roomType = null, ?string $roomId = null, int $limit = 50): Collection;

    public function countCreatedToday(User $user, ?string $configId = null): int;

    /** @return Collection<int, RedEnvelope> */
    public function dueForExpiry(int $limit = 100): Collection;

    public function claimedTotal(RedEnvelope $envelope): int;
}
