<?php

namespace App\Features\Games\Plugins\LuckyDice\Actions;

use App\Features\Games\Plugins\LuckyDice\DTOs\LuckyDiceDTO;
use App\Features\Games\Plugins\LuckyDice\Models\LuckyDiceBet;
use App\Features\Games\Plugins\LuckyDice\Services\LuckyDiceService;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\Wallet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class LuckyDiceAction
{
    public function __construct(private LuckyDiceService $service) {}

    /** @return array<string, mixed> */
    public function config(User $user): array
    {
        return $this->service->config($user);
    }

    /**
     * @return array{bet: LuckyDiceBet, wallet: Wallet, replay: bool}
     */
    public function play(User $user, LuckyDiceDTO $data): array
    {
        return $this->service->play($user, $data);
    }

    public function history(User $user, int $perPage = 30): LengthAwarePaginator
    {
        return $this->service->history($user, $perPage);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function leaderboard(string $period = 'daily', int $limit = 50, string $type = 'winners'): Collection
    {
        return $this->service->leaderboard($period, $limit, $type);
    }
}
