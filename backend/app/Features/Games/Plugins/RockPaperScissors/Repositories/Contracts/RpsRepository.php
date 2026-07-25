<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Repositories\Contracts;

use App\Features\Games\Plugins\RockPaperScissors\Models\RpsConfig;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMove;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsRound;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface RpsRepository
{
    public function findDefaultConfig(): ?RpsConfig;

    public function findMatch(string $matchId): ?RpsMatch;

    public function findMatchByIdempotency(User $user, string $key): ?RpsMatch;

    public function findAcceptedMatchByIdempotency(User $user, string $key): ?RpsMatch;

    public function findMoveByIdempotency(User $user, string $key): ?RpsMove;

    /** @param array<string, mixed> $attributes */
    public function createMatch(array $attributes): RpsMatch;

    /** @param array<string, mixed> $attributes */
    public function createRound(array $attributes): RpsRound;

    /** @param array<string, mixed> $attributes */
    public function createMove(array $attributes): RpsMove;

    /**
     * Oldest waiting open challenge another player can be paired into.
     */
    public function findOpenChallenge(User $user, string $mode, int $entryFeeCoins, string $configId): ?RpsMatch;

    public function countCreatedToday(User $user, string $configId): int;

    public function history(User $user, int $perPage = 30): LengthAwarePaginator;

    /** @return Collection<int, array<string, mixed>> */
    public function leaderboard(string $period = 'daily', int $limit = 50, ?string $mode = null, ?string $roomId = null): Collection;

    /** @return array<string, mixed> */
    public function statistics(User $user, ?string $configId = null): array;
}
