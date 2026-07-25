<?php

namespace App\Features\Games\Plugins\LuckyWheel\Repositories\Contracts;

use App\Features\Games\Plugins\LuckyWheel\Models\LuckyWheelConfig;
use App\Features\Games\Plugins\LuckyWheel\Models\LuckyWheelSpin;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface LuckyWheelRepository
{
    public function activeConfig(): ?LuckyWheelConfig;

    public function findSpinByIdempotency(User $user, string $idempotencyKey): ?LuckyWheelSpin;

    public function createSpin(array $attributes): LuckyWheelSpin;

    public function history(User $user, int $perPage = 30): LengthAwarePaginator;

    /** @return Collection<int, array<string, mixed>> */
    public function leaderboard(string $period = 'daily', int $limit = 50): Collection;

    public function countSpinsToday(User $user, ?string $configId = null): int;

    public function lastSpinAt(User $user, ?string $configId = null): ?Carbon;
}
