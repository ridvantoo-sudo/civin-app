<?php

namespace App\Features\Games\Plugins\LuckyWheel\Actions;

use App\Features\Games\Plugins\LuckyWheel\DTOs\LuckyWheelDTO;
use App\Features\Games\Plugins\LuckyWheel\Models\LuckyWheelSpin;
use App\Features\Games\Plugins\LuckyWheel\Services\LuckyWheelService;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\Wallet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class LuckyWheelAction
{
    public function __construct(private LuckyWheelService $service) {}

    /** @return array<string, mixed> */
    public function config(User $user): array
    {
        return $this->service->config($user);
    }

    /**
     * @return array{spin: LuckyWheelSpin, wallet: Wallet, replay: bool}
     */
    public function spin(User $user, LuckyWheelDTO $data): array
    {
        return $this->service->spin($user, $data);
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
}
