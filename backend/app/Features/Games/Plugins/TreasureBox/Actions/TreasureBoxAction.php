<?php

namespace App\Features\Games\Plugins\TreasureBox\Actions;

use App\Features\Games\Models\TreasureBox;
use App\Features\Games\Models\TreasureBoxParticipant;
use App\Features\Games\Plugins\TreasureBox\DTOs\CreateTreasureBoxDTO;
use App\Features\Games\Plugins\TreasureBox\DTOs\JoinTreasureBoxDTO;
use App\Features\Games\Plugins\TreasureBox\Services\TreasureBoxService;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\Wallet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class TreasureBoxAction
{
    public function __construct(private TreasureBoxService $service) {}

    /** @return array<string, mixed> */
    public function config(User $user): array
    {
        return $this->service->config($user);
    }

    /**
     * @return array{treasure: TreasureBox, wallet: Wallet, replay: bool}
     */
    public function create(User $user, CreateTreasureBoxDTO $data): array
    {
        return $this->service->create($user, $data);
    }

    /**
     * @return array{treasure: TreasureBox, participant: TreasureBoxParticipant, wallet: Wallet, replay: bool}
     */
    public function join(User $user, JoinTreasureBoxDTO $data): array
    {
        return $this->service->join($user, $data);
    }

    public function history(User $user, string $type = 'all', int $perPage = 30): LengthAwarePaginator
    {
        return $this->service->history($user, $type, $perPage);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function leaderboard(string $period = 'daily', string $type = 'winners', int $limit = 50): Collection
    {
        return $this->service->leaderboard($period, $type, $limit);
    }

    /** @return array<string, mixed> */
    public function statistics(User $user): array
    {
        return $this->service->statistics($user);
    }
}
