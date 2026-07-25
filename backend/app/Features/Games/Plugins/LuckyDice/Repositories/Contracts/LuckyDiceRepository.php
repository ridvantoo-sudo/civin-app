<?php

namespace App\Features\Games\Plugins\LuckyDice\Repositories\Contracts;

use App\Features\Games\Plugins\LuckyDice\Models\LuckyDiceBet;
use App\Features\Games\Plugins\LuckyDice\Models\LuckyDiceConfig;
use App\Features\Games\Plugins\LuckyDice\Models\LuckyDiceResult;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface LuckyDiceRepository
{
    public function activeConfig(): ?LuckyDiceConfig;

    public function findBetByIdempotency(User $user, string $idempotencyKey): ?LuckyDiceBet;

    public function createBet(array $attributes): LuckyDiceBet;

    public function createResult(array $attributes): LuckyDiceResult;

    public function history(User $user, int $perPage = 30): LengthAwarePaginator;

    /** @return Collection<int, array<string, mixed>> */
    public function leaderboard(string $period = 'daily', int $limit = 50, string $type = 'winners'): Collection;

    public function countBetsToday(User $user, ?string $configId = null): int;

    public function lastBetAt(User $user, ?string $configId = null): ?Carbon;
}
