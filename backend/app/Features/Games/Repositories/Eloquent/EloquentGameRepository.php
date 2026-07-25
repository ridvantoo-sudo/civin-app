<?php

namespace App\Features\Games\Repositories\Eloquent;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GameAnalyticsEvent;
use App\Features\Games\Models\GameLeaderboardEntry;
use App\Features\Games\Models\GameMatch;
use App\Features\Games\Models\GamePlay;
use App\Features\Games\Repositories\Contracts\GameRepository;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentGameRepository implements GameRepository
{
    public function catalog(): Collection
    {
        return Game::query()
            ->where('status', Game::STATUS_ACTIVE)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function findBySlug(string $slug): ?Game
    {
        return Game::query()->where('slug', $slug)->first();
    }

    public function findActiveBySlug(string $slug): ?Game
    {
        return Game::query()
            ->where('slug', $slug)
            ->where('status', Game::STATUS_ACTIVE)
            ->first();
    }

    public function createPlay(array $attributes): GamePlay
    {
        return GamePlay::query()->create($attributes);
    }

    public function history(User $user, ?string $gameSlug, int $perPage = 30): LengthAwarePaginator
    {
        $query = GamePlay::query()
            ->where('user_id', $user->getKey())
            ->with('game')
            ->orderByDesc('created_at');

        if ($gameSlug !== null && $gameSlug !== '') {
            $query->whereHas('game', fn ($builder) => $builder->where('slug', $gameSlug));
        }

        return $query->paginate(max(1, min($perPage, 100)));
    }

    public function leaderboard(?string $gameSlug, string $period, int $limit = 50): Collection
    {
        $periodKey = $this->periodKey($period);
        $limit = max(1, min($limit, 100));

        if ($gameSlug === null || $gameSlug === '') {
            return $this->aggregateGlobalLeaderboard($period, $periodKey, $limit);
        }

        $game = $this->findBySlug($gameSlug);
        if ($game === null) {
            return collect();
        }

        return GameLeaderboardEntry::query()
            ->where('period', $period)
            ->where('period_key', $periodKey)
            ->where('game_id', $game->getKey())
            ->with(['user.profile', 'game'])
            ->orderByDesc('score')
            ->orderByDesc('wins')
            ->limit($limit)
            ->get()
            ->values()
            ->map(fn (GameLeaderboardEntry $entry, int $index): array => $this->serializeLeaderboardEntry($entry, $index + 1));
    }

    public function userStats(User $user, ?string $gameSlug = null): array
    {
        $plays = GamePlay::query()->where('user_id', $user->getKey());
        if ($gameSlug !== null && $gameSlug !== '') {
            $plays->whereHas('game', fn ($builder) => $builder->where('slug', $gameSlug));
        }

        $rows = $plays->get();
        $wins = $rows->where('result', GamePlay::RESULT_WON)->count();
        $bet = (int) $rows->sum('bet_coins');
        $reward = (int) $rows->sum('reward_coins');

        return [
            'plays' => $rows->count(),
            'wins' => $wins,
            'losses' => $rows->where('result', GamePlay::RESULT_LOST)->count(),
            'draws' => $rows->where('result', GamePlay::RESULT_DRAW)->count(),
            'coins_wagered' => $bet,
            'coins_won' => $reward,
            'coins_net' => $reward - $bet,
            'win_rate' => $rows->count() === 0 ? 0 : round(($wins / $rows->count()) * 100, 2),
        ];
    }

    public function bumpLeaderboard(
        User $user,
        Game $game,
        int $betCoins,
        int $rewardCoins,
        bool $won,
    ): void {
        $net = $rewardCoins - $betCoins;
        $scoreDelta = max(0, $rewardCoins) + ($won ? 25 : 0);

        foreach ([
            [GameLeaderboardEntry::PERIOD_DAILY, $this->periodKey(GameLeaderboardEntry::PERIOD_DAILY)],
            [GameLeaderboardEntry::PERIOD_WEEKLY, $this->periodKey(GameLeaderboardEntry::PERIOD_WEEKLY)],
            [GameLeaderboardEntry::PERIOD_ALL, 'all'],
        ] as [$period, $periodKey]) {
            $this->bumpEntry($user->getKey(), $game->getKey(), $period, $periodKey, $betCoins, $net, $won, $scoreDelta);
        }
    }

    public function trackAnalytics(?User $user, ?Game $game, string $event, ?array $payload = null): void
    {
        GameAnalyticsEvent::query()->create([
            'user_id' => $user?->getKey(),
            'game_id' => $game?->getKey(),
            'event' => $event,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }

    public function findMatch(string $matchId): ?GameMatch
    {
        return GameMatch::query()
            ->with(['game', 'players.user.profile', 'host', 'winner'])
            ->find($matchId);
    }

    public function createMatch(array $attributes): GameMatch
    {
        return GameMatch::query()->create($attributes);
    }

    public function periodKey(string $period): string
    {
        return match ($period) {
            GameLeaderboardEntry::PERIOD_WEEKLY => now()->format('o-\WW'),
            GameLeaderboardEntry::PERIOD_ALL => 'all',
            default => now()->toDateString(),
        };
    }

    private function bumpEntry(
        string $userId,
        string $gameId,
        string $period,
        string $periodKey,
        int $betCoins,
        int $net,
        bool $won,
        int $scoreDelta,
    ): void {
        DB::transaction(function () use ($userId, $gameId, $period, $periodKey, $betCoins, $net, $won, $scoreDelta): void {
            $entry = GameLeaderboardEntry::query()->firstOrCreate(
                [
                    'user_id' => $userId,
                    'game_id' => $gameId,
                    'period' => $period,
                    'period_key' => $periodKey,
                ],
                [
                    'plays' => 0,
                    'wins' => 0,
                    'coins_wagered' => 0,
                    'coins_net' => 0,
                    'score' => 0,
                ],
            );

            $locked = GameLeaderboardEntry::query()->whereKey($entry->getKey())->lockForUpdate()->first();
            if ($locked === null) {
                return;
            }

            $locked->forceFill([
                'plays' => $locked->plays + 1,
                'wins' => $locked->wins + ($won ? 1 : 0),
                'coins_wagered' => $locked->coins_wagered + $betCoins,
                'coins_net' => $locked->coins_net + $net,
                'score' => $locked->score + $scoreDelta,
            ])->save();
        });
    }

    private function aggregateGlobalLeaderboard(string $period, string $periodKey, int $limit): Collection
    {
        $rows = GameLeaderboardEntry::query()
            ->selectRaw('user_id, SUM(plays) as plays, SUM(wins) as wins, SUM(coins_wagered) as coins_wagered, SUM(coins_net) as coins_net, SUM(score) as score')
            ->where('period', $period)
            ->where('period_key', $periodKey)
            ->whereNotNull('game_id')
            ->groupBy('user_id')
            ->orderByDesc('score')
            ->orderByDesc('wins')
            ->limit($limit)
            ->get();

        $users = User::query()
            ->with('profile')
            ->whereIn('id', $rows->pluck('user_id'))
            ->get()
            ->keyBy('id');

        return $rows->values()->map(function ($row, int $index) use ($users, $period, $periodKey): array {
            $user = $users->get($row->user_id);

            return [
                'rank' => $index + 1,
                'user_id' => $row->user_id,
                'username' => $user?->username,
                'display_name' => $user?->profile?->display_name ?? $user?->username,
                'avatar_url' => $user?->profile?->avatar_url,
                'game_id' => null,
                'game_slug' => null,
                'plays' => (int) $row->plays,
                'wins' => (int) $row->wins,
                'coins_wagered' => (int) $row->coins_wagered,
                'coins_net' => (int) $row->coins_net,
                'score' => (int) $row->score,
                'period' => $period,
                'period_key' => $periodKey,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLeaderboardEntry(GameLeaderboardEntry $entry, int $rank): array
    {
        return [
            'rank' => $rank,
            'user_id' => $entry->user_id,
            'username' => $entry->user?->username,
            'display_name' => $entry->user?->profile?->display_name ?? $entry->user?->username,
            'avatar_url' => $entry->user?->profile?->avatar_url,
            'game_id' => $entry->game_id,
            'game_slug' => $entry->game?->slug,
            'plays' => $entry->plays,
            'wins' => $entry->wins,
            'coins_wagered' => $entry->coins_wagered,
            'coins_net' => $entry->coins_net,
            'score' => $entry->score,
            'period' => $entry->period,
            'period_key' => $entry->period_key,
        ];
    }
}
