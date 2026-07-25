<?php

namespace App\Features\Games\Plugins\SlotMachine\Actions;

use App\Features\Games\Plugins\SlotMachine\DTOs\SlotMachineDTO;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineSpin;
use App\Features\Games\Plugins\SlotMachine\Services\SlotMachineService;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\Wallet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class SlotMachineAction
{
    public function __construct(private SlotMachineService $service) {}

    /** @return array<string, mixed> */
    public function config(User $user): array
    {
        return $this->service->config($user);
    }

    /**
     * @return array{spin: SlotMachineSpin, wallet: Wallet, replay: bool}
     */
    public function play(User $user, SlotMachineDTO $data): array
    {
        return $this->service->play($user, $data);
    }

    public function history(User $user, int $perPage = 30): LengthAwarePaginator
    {
        return $this->service->history($user, $perPage);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function leaderboard(string $period = 'daily', int $limit = 50): Collection
    {
        return $this->service->leaderboard($period, $limit);
    }

    /** @return array<string, mixed> */
    public function statistics(User $user): array
    {
        return $this->service->statistics($user);
    }

    /** @return array<string, mixed> */
    public function jackpot(User $user): array
    {
        return $this->service->jackpot($user);
    }
}
