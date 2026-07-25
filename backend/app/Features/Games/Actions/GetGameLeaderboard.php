<?php

namespace App\Features\Games\Actions;

use App\Features\Games\Services\GameService;
use Illuminate\Support\Collection;

final readonly class GetGameLeaderboard
{
    public function __construct(private GameService $games) {}

    /** @return Collection<int, array<string, mixed>> */
    public function execute(?string $gameSlug = null, string $period = 'daily', int $limit = 50): Collection
    {
        return $this->games->leaderboard($gameSlug, $period, $limit);
    }
}
