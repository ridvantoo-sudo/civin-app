<?php

namespace App\Features\Games\Plugins\LuckyNumber\Actions;

use App\Features\Games\Plugins\LuckyNumber\DTOs\LuckyNumberDTO;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberTicket;
use App\Features\Games\Plugins\LuckyNumber\Services\LuckyNumberService;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\Wallet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class LuckyNumberAction
{
    public function __construct(private LuckyNumberService $service) {}

    /** @return array<string, mixed> */
    public function config(User $user): array
    {
        return $this->service->config($user);
    }

    /**
     * @return array{ticket: LuckyNumberTicket, wallet: Wallet, replay: bool}
     */
    public function play(User $user, LuckyNumberDTO $data): array
    {
        return $this->service->play($user, $data);
    }

    /**
     * @return array{ticket: LuckyNumberTicket, wallet: Wallet, replay: bool}
     */
    public function quickPick(User $user, LuckyNumberDTO $data): array
    {
        return $this->service->quickPick($user, $data);
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

    public function draws(?User $user = null, int $perPage = 30): LengthAwarePaginator
    {
        return $this->service->draws($user, $perPage);
    }
}
