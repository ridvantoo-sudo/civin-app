<?php

namespace App\Features\Security\Repositories\Contracts;

use App\Features\Security\DTO\SecurityEventData;
use App\Features\Security\Models\SecurityAlert;
use App\Features\Security\Models\SecurityEvent;
use Illuminate\Support\Collection;

interface SecurityRepository
{
    /**
     * Persists a normalized event. Returns null when the event was a duplicate
     * (idempotency key collision), which callers treat as "already handled".
     */
    public function appendEvent(SecurityEventData $event): ?SecurityEvent;

    public function markAnalyzed(string $eventId, float $riskContribution): void;

    /**
     * @return Collection<int, SecurityEvent>
     */
    public function historyForUser(string $userId, int $limit = 50): Collection;

    /**
     * @return Collection<int, SecurityAlert>
     */
    public function alertsForUser(string $userId, int $limit = 50): Collection;

    /**
     * @return array<string, mixed>
     */
    public function overview(): array;

    public function purgeOlderThan(string $table, string $column, int $days): int;
}
