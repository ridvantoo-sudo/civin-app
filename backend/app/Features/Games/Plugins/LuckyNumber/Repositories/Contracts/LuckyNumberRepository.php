<?php

namespace App\Features\Games\Plugins\LuckyNumber\Repositories\Contracts;

use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberConfig;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberDraw;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberJackpot;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberStatistic;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberTicket;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface LuckyNumberRepository
{
    public function activeConfig(): ?LuckyNumberConfig;

    public function findTicketByIdempotency(User $user, string $idempotencyKey): ?LuckyNumberTicket;

    /** @param  array<string, mixed>  $attributes */
    public function createTicket(array $attributes): LuckyNumberTicket;

    /** @param  array<string, mixed>  $attributes */
    public function createDraw(array $attributes): LuckyNumberDraw;

    /** @return Collection<int, LuckyNumberDraw> */
    public function findPendingScheduledDraws(int $limit = 50): Collection;

    public function history(User $user, int $perPage = 30): LengthAwarePaginator;

    /** @return Collection<int, array<string, mixed>> */
    public function leaderboard(string $period = 'daily', int $limit = 50): Collection;

    /** @return Collection<int, LuckyNumberJackpot> */
    public function jackpots(LuckyNumberConfig $config): Collection;

    public function findJackpot(LuckyNumberConfig $config, string $type, ?string $roomId = null): ?LuckyNumberJackpot;

    public function statistics(User $user, ?string $configId = null): ?LuckyNumberStatistic;

    /** @param  array<string, mixed>  $attributes */
    public function upsertStatistics(User $user, ?string $configId, array $attributes): LuckyNumberStatistic;

    public function countTicketsToday(User $user, ?string $configId = null): int;

    public function lastTicketAt(User $user, ?string $configId = null): ?Carbon;

    public function lastTicket(User $user, ?string $configId = null): ?LuckyNumberTicket;

    public function draws(?User $user, int $perPage = 30): LengthAwarePaginator;
}
