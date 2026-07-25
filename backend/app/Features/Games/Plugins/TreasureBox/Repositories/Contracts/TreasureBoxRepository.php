<?php

namespace App\Features\Games\Plugins\TreasureBox\Repositories\Contracts;

use App\Features\Games\Models\TreasureBox;
use App\Features\Games\Models\TreasureBoxParticipant;
use App\Features\Games\Models\TreasureBoxReward;
use App\Features\Games\Models\TreasureBoxStatistic;
use App\Features\Games\Models\TreasureBoxWinner;
use App\Features\Games\Plugins\TreasureBox\Models\TreasureBoxConfig;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TreasureBoxRepository
{
    public function activeConfig(): ?TreasureBoxConfig;

    public function findTreasure(string $treasureBoxId): ?TreasureBox;

    public function findByIdempotency(User $creator, string $idempotencyKey): ?TreasureBox;

    public function findParticipantByIdempotency(User $user, string $idempotencyKey): ?TreasureBoxParticipant;

    public function findParticipantFor(TreasureBox $treasure, User $user): ?TreasureBoxParticipant;

    public function findWinnerByIdempotency(User $user, string $idempotencyKey): ?TreasureBoxWinner;

    /** @param  array<string, mixed>  $attributes */
    public function createTreasure(array $attributes): TreasureBox;

    /** @param  array<string, mixed>  $attributes */
    public function createParticipant(array $attributes): TreasureBoxParticipant;

    /** @param  array<string, mixed>  $attributes */
    public function createReward(array $attributes): TreasureBoxReward;

    /** @param  array<string, mixed>  $attributes */
    public function createWinner(array $attributes): TreasureBoxWinner;

    public function history(User $user, string $type = 'all', int $perPage = 30): LengthAwarePaginator;

    /** @return Collection<int, array<string, mixed>> */
    public function leaderboard(string $period = 'daily', string $type = 'winners', int $limit = 50): Collection;

    public function statisticsFor(User $user): TreasureBoxStatistic;

    public function countCreatedToday(User $user, ?string $configId = null): int;

    /** @return Collection<int, TreasureBox> */
    public function dueForScheduleStart(int $limit = 100): Collection;

    /** @return Collection<int, TreasureBox> */
    public function dueForDraw(int $limit = 100): Collection;

    /** @return Collection<int, TreasureBox> */
    public function dueForExpiry(int $limit = 100): Collection;

    /** @return Collection<int, TreasureBoxParticipant> */
    public function participantsFor(TreasureBox $treasure): Collection;
}
