<?php

namespace App\Features\Games\Plugins\SlotMachine\Repositories\Contracts;

use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineConfig;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineJackpot;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineSession;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineSpin;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineStatistic;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface SlotMachineRepository
{
    public function activeConfig(): ?SlotMachineConfig;

    public function findSpinByIdempotency(User $user, string $idempotencyKey): ?SlotMachineSpin;

    /** @param  array<string, mixed>  $attributes */
    public function createSpin(array $attributes): SlotMachineSpin;

    /** @param  array<string, mixed>  $attributes */
    public function createSession(array $attributes): SlotMachineSession;

    public function findActiveSession(User $user, ?string $configId = null): ?SlotMachineSession;

    public function history(User $user, int $perPage = 30): LengthAwarePaginator;

    /** @return Collection<int, array<string, mixed>> */
    public function leaderboard(string $period = 'daily', int $limit = 50): Collection;

    /** @return Collection<int, SlotMachineJackpot> */
    public function jackpots(SlotMachineConfig $config): Collection;

    public function findJackpot(SlotMachineConfig $config, string $type, ?string $roomId = null): ?SlotMachineJackpot;

    public function statistics(User $user, ?string $configId = null): ?SlotMachineStatistic;

    /** @param  array<string, mixed>  $attributes */
    public function upsertStatistics(User $user, ?string $configId, array $attributes): SlotMachineStatistic;

    public function countSpinsToday(User $user, ?string $configId = null): int;

    public function lastSpinAt(User $user, ?string $configId = null): ?Carbon;

    public function freeSpinsRemaining(User $user, ?string $configId = null): int;
}
