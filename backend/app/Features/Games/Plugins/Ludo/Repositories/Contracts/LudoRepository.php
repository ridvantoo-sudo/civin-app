<?php

namespace App\Features\Games\Plugins\Ludo\Repositories\Contracts;

use App\Features\Games\Plugins\Ludo\Models\LudoConfig;
use App\Features\Games\Plugins\Ludo\Models\LudoMatch;
use App\Features\Games\Plugins\Ludo\Models\LudoMove;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface LudoRepository
{
    public function config(): ?LudoConfig;

    public function match(string $id): ?LudoMatch;

    public function createMatch(array $attributes): LudoMatch;

    public function findAction(User $user, string $key): LudoMatch|LudoMove|null;

    public function history(User $user, int $perPage): LengthAwarePaginator;

    public function leaderboard(string $period, int $limit, ?string $mode): Collection;

    public function statistics(User $user): array;

    public function tournaments(int $perPage): LengthAwarePaginator;

    public function replays(User $user, int $perPage): LengthAwarePaginator;
}
