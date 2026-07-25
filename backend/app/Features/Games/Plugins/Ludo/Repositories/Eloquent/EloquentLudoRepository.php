<?php

namespace App\Features\Games\Plugins\Ludo\Repositories\Eloquent;

use App\Features\Games\Plugins\Ludo\Models\LudoConfig;
use App\Features\Games\Plugins\Ludo\Models\LudoHistory;
use App\Features\Games\Plugins\Ludo\Models\LudoMatch;
use App\Features\Games\Plugins\Ludo\Models\LudoMove;
use App\Features\Games\Plugins\Ludo\Models\LudoReplay;
use App\Features\Games\Plugins\Ludo\Models\LudoStatistic;
use App\Features\Games\Plugins\Ludo\Models\LudoTournament;
use App\Features\Games\Plugins\Ludo\Repositories\Contracts\LudoRepository;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentLudoRepository implements LudoRepository
{
    public function config(): ?LudoConfig
    {
        return LudoConfig::query()->where('enabled', true)->where('status', 'active')
            ->orderByDesc('is_default')->latest()->first();
    }

    public function match(string $id): ?LudoMatch
    {
        return LudoMatch::query()->with(['players', 'turns', 'moves', 'rewards'])->find($id);
    }

    public function createMatch(array $attributes): LudoMatch
    {
        return LudoMatch::query()->create($attributes);
    }

    public function findAction(User $user, string $key): LudoMatch|LudoMove|null
    {
        return LudoMatch::query()->where('host_id', $user->getKey())->where('idempotency_key', $key)->first()
            ?? LudoMove::query()->where('player_id', $user->getKey())->where('idempotency_key', $key)->first();
    }

    public function history(User $user, int $perPage): LengthAwarePaginator
    {
        return LudoHistory::query()->where('user_id', $user->getKey())->latest()->paginate($perPage);
    }

    public function leaderboard(string $period, int $limit, ?string $mode): Collection
    {
        $from = match ($period) {
            'weekly' => now()->startOfWeek(),
            'monthly' => now()->startOfMonth(),
            'all_time' => null,
            default => now()->startOfDay(),
        };

        return LudoHistory::query()
            ->select('user_id', DB::raw('COUNT(*) matches'), DB::raw('SUM(CASE WHEN is_winner THEN 1 ELSE 0 END) wins'), DB::raw('SUM(reward_coins) reward_coins'))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($mode, fn ($q) => $q->where('mode', $mode))
            ->groupBy('user_id')->orderByDesc('wins')->limit($limit)->get()
            ->values()->map(fn ($row, int $index): array => [
                'rank' => $index + 1, 'user_id' => $row->user_id,
                'matches' => (int) $row->matches, 'wins' => (int) $row->wins,
                'reward_coins' => (int) $row->reward_coins,
            ]);
    }

    public function statistics(User $user): array
    {
        return LudoStatistic::query()->firstOrCreate(['user_id' => $user->getKey()])->toArray();
    }

    public function tournaments(int $perPage): LengthAwarePaginator
    {
        return LudoTournament::query()->latest('scheduled_at')->paginate($perPage);
    }

    public function replays(User $user, int $perPage): LengthAwarePaginator
    {
        return LudoReplay::query()->whereHas('match.players', fn ($q) => $q->where('user_id', $user->getKey()))
            ->latest()->paginate($perPage);
    }
}
