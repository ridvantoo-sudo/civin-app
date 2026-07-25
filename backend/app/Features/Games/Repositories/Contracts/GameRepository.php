<?php

namespace App\Features\Games\Repositories\Contracts;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GameMatch;
use App\Features\Games\Models\GamePlay;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface GameRepository
{
    /** @return Collection<int, Game> */
    public function catalog(): Collection;

    public function findBySlug(string $slug): ?Game;

    public function findActiveBySlug(string $slug): ?Game;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createPlay(array $attributes): GamePlay;

    public function history(User $user, ?string $gameSlug, int $perPage = 30): LengthAwarePaginator;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function leaderboard(?string $gameSlug, string $period, int $limit = 50): Collection;

    /**
     * @return array<string, mixed>
     */
    public function userStats(User $user, ?string $gameSlug = null): array;

    public function bumpLeaderboard(
        User $user,
        Game $game,
        int $betCoins,
        int $rewardCoins,
        bool $won,
    ): void;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function trackAnalytics(?User $user, ?Game $game, string $event, ?array $payload = null): void;

    public function findMatch(string $matchId): ?GameMatch;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createMatch(array $attributes): GameMatch;
}
