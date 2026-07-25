<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Repositories\Eloquent;

use App\Features\Games\Plugins\RockPaperScissors\Models\RpsConfig;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsHistory;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMove;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsRound;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsStatistic;
use App\Features\Games\Plugins\RockPaperScissors\Repositories\Contracts\RpsRepository;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentRpsRepository implements RpsRepository
{
    public function findDefaultConfig(): ?RpsConfig
    {
        return RpsConfig::query()
            ->where('status', RpsConfig::STATUS_ACTIVE)
            ->where('enabled', true)
            ->where('is_default', true)
            ->first()
            ?? RpsConfig::query()
                ->where('status', RpsConfig::STATUS_ACTIVE)
                ->where('enabled', true)
                ->orderByDesc('created_at')
                ->first();
    }

    public function findMatch(string $matchId): ?RpsMatch
    {
        return RpsMatch::query()
            ->with(['challenger', 'opponent', 'rounds', 'rewards'])
            ->find($matchId);
    }

    public function findMatchByIdempotency(User $user, string $key): ?RpsMatch
    {
        return RpsMatch::query()
            ->where('challenger_id', $user->getKey())
            ->where('idempotency_key', $key)
            ->with(['challenger', 'opponent', 'rounds', 'rewards'])
            ->first();
    }

    public function findAcceptedMatchByIdempotency(User $user, string $key): ?RpsMatch
    {
        return RpsMatch::query()
            ->where('opponent_id', $user->getKey())
            ->where('opponent_idempotency_key', $key)
            ->with(['challenger', 'opponent', 'rounds', 'rewards'])
            ->first();
    }

    public function findMoveByIdempotency(User $user, string $key): ?RpsMove
    {
        return RpsMove::query()
            ->where('user_id', $user->getKey())
            ->where('idempotency_key', $key)
            ->with(['match', 'round'])
            ->first();
    }

    public function createMatch(array $attributes): RpsMatch
    {
        return RpsMatch::query()->create($attributes);
    }

    public function createRound(array $attributes): RpsRound
    {
        return RpsRound::query()->create($attributes);
    }

    public function createMove(array $attributes): RpsMove
    {
        return RpsMove::query()->create($attributes);
    }

    public function findOpenChallenge(User $user, string $mode, int $entryFeeCoins, string $configId): ?RpsMatch
    {
        return RpsMatch::query()
            ->where('config_id', $configId)
            ->where('status', RpsMatch::STATUS_MATCHING)
            ->whereIn('matchmaking', RpsMatch::OPEN_MATCHMAKING)
            ->where('mode', $mode)
            ->where('entry_fee_coins', $entryFeeCoins)
            ->whereNull('opponent_id')
            ->where('challenger_id', '!=', $user->getKey())
            ->where(function ($query): void {
                $query->whereNull('accept_deadline_at')->orWhere('accept_deadline_at', '>', now());
            })
            ->orderBy('created_at')
            ->first();
    }

    public function countCreatedToday(User $user, string $configId): int
    {
        return RpsMatch::query()
            ->where('challenger_id', $user->getKey())
            ->where('config_id', $configId)
            ->whereDate('created_at', now()->toDateString())
            ->count();
    }

    public function history(User $user, int $perPage = 30): LengthAwarePaginator
    {
        return RpsHistory::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function leaderboard(string $period = 'daily', int $limit = 50, ?string $mode = null, ?string $roomId = null): Collection
    {
        $from = match ($period) {
            'weekly' => now()->startOfWeek(),
            'monthly' => now()->startOfMonth(),
            'all_time', 'all' => null,
            'tournament' => now()->subDays(30),
            default => now()->startOfDay(),
        };

        $query = RpsHistory::query()
            ->select([
                'user_id',
                DB::raw('COUNT(*) as matches'),
                DB::raw('SUM(CASE WHEN is_winner THEN 1 ELSE 0 END) as wins'),
                DB::raw('SUM(rounds_won) as rounds_won'),
                DB::raw('SUM(reward_coins) as reward_coins'),
                DB::raw('SUM(reward_diamonds) as reward_diamonds'),
            ])
            ->groupBy('user_id')
            ->orderByDesc('wins')
            ->orderByDesc('rounds_won')
            ->limit($limit);

        if ($from !== null) {
            $query->where('created_at', '>=', $from);
        }

        if ($mode !== null) {
            $query->where('mode', $mode);
        }

        if ($roomId !== null) {
            $query->whereHas('match', fn ($q) => $q->where('room_id', $roomId));
        }

        return $query->get()->values()->map(fn ($row, int $i): array => [
            'rank' => $i + 1,
            'user_id' => $row->user_id,
            'matches' => (int) $row->matches,
            'wins' => (int) $row->wins,
            'rounds_won' => (int) $row->rounds_won,
            'reward_coins' => (int) $row->reward_coins,
            'reward_diamonds' => (int) $row->reward_diamonds,
            'win_rate' => (int) $row->matches > 0
                ? round(((int) $row->wins / (int) $row->matches) * 100, 2)
                : 0.0,
            'period' => $period,
        ]);
    }

    public function statistics(User $user, ?string $configId = null): array
    {
        $stat = RpsStatistic::query()
            ->where('user_id', $user->getKey())
            ->when($configId, fn ($q) => $q->where('config_id', $configId))
            ->orderByDesc('updated_at')
            ->first();

        if ($stat === null) {
            return [
                'matches_played' => 0,
                'matches_won' => 0,
                'matches_lost' => 0,
                'matches_drawn' => 0,
                'forfeits' => 0,
                'rounds_played' => 0,
                'rounds_won' => 0,
                'rounds_lost' => 0,
                'rounds_drawn' => 0,
                'current_streak' => 0,
                'best_streak' => 0,
                'win_rate' => 0.0,
                'average_response_ms' => 0.0,
                'coins_wagered' => 0,
                'coins_won' => 0,
                'diamonds_won' => 0,
                'tournaments_played' => 0,
                'tournaments_won' => 0,
                'move_distribution' => [],
            ];
        }

        return [
            'matches_played' => (int) $stat->matches_played,
            'matches_won' => (int) $stat->matches_won,
            'matches_lost' => (int) $stat->matches_lost,
            'matches_drawn' => (int) $stat->matches_drawn,
            'forfeits' => (int) $stat->forfeits,
            'rounds_played' => (int) $stat->rounds_played,
            'rounds_won' => (int) $stat->rounds_won,
            'rounds_lost' => (int) $stat->rounds_lost,
            'rounds_drawn' => (int) $stat->rounds_drawn,
            'current_streak' => (int) $stat->current_streak,
            'best_streak' => (int) $stat->best_streak,
            'win_rate' => (float) $stat->win_rate,
            'average_response_ms' => (float) $stat->average_response_ms,
            'coins_wagered' => (int) $stat->coins_wagered,
            'coins_won' => (int) $stat->coins_won,
            'diamonds_won' => (int) $stat->diamonds_won,
            'tournaments_played' => (int) $stat->tournaments_played,
            'tournaments_won' => (int) $stat->tournaments_won,
            'move_distribution' => $stat->move_distribution ?? [],
        ];
    }
}
