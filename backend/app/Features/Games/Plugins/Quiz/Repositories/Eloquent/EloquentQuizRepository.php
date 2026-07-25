<?php

namespace App\Features\Games\Plugins\Quiz\Repositories\Eloquent;

use App\Features\Games\Plugins\Quiz\Models\QuizAnswer;
use App\Features\Games\Plugins\Quiz\Models\QuizConfig;
use App\Features\Games\Plugins\Quiz\Models\QuizHistory;
use App\Features\Games\Plugins\Quiz\Models\QuizPlayer;
use App\Features\Games\Plugins\Quiz\Models\QuizSession;
use App\Features\Games\Plugins\Quiz\Models\QuizStatistic;
use App\Features\Games\Plugins\Quiz\Repositories\Contracts\QuizRepository;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentQuizRepository implements QuizRepository
{
    public function findDefaultConfig(): ?QuizConfig
    {
        return QuizConfig::query()
            ->where('status', QuizConfig::STATUS_ACTIVE)
            ->where('enabled', true)
            ->where('is_default', true)
            ->first()
            ?? QuizConfig::query()
                ->where('status', QuizConfig::STATUS_ACTIVE)
                ->where('enabled', true)
                ->orderByDesc('created_at')
                ->first();
    }

    public function findSession(string $sessionId): ?QuizSession
    {
        return QuizSession::query()
            ->with(['host', 'category', 'players.user', 'scores', 'rewards'])
            ->find($sessionId);
    }

    public function findSessionByIdempotency(User $user, string $key): ?QuizSession
    {
        return QuizSession::query()
            ->where('host_id', $user->getKey())
            ->where('idempotency_key', $key)
            ->with(['host', 'category', 'players.user', 'scores', 'rewards'])
            ->first();
    }

    public function findPlayerByIdempotency(User $user, string $key): ?QuizPlayer
    {
        return QuizPlayer::query()
            ->where('user_id', $user->getKey())
            ->where('idempotency_key', $key)
            ->with(['session', 'user'])
            ->first();
    }

    public function findAnswerByIdempotency(User $user, string $key): ?QuizAnswer
    {
        return QuizAnswer::query()
            ->where('user_id', $user->getKey())
            ->where('idempotency_key', $key)
            ->with(['session', 'player', 'question'])
            ->first();
    }

    public function createSession(array $attributes): QuizSession
    {
        return QuizSession::query()->create($attributes);
    }

    public function createPlayer(array $attributes): QuizPlayer
    {
        return QuizPlayer::query()->create($attributes);
    }

    public function createAnswer(array $attributes): QuizAnswer
    {
        return QuizAnswer::query()->create($attributes);
    }

    public function countCreatedToday(User $user, string $configId): int
    {
        return QuizSession::query()
            ->where('host_id', $user->getKey())
            ->where('config_id', $configId)
            ->whereDate('created_at', now()->toDateString())
            ->count();
    }

    public function history(User $user, int $perPage = 30): LengthAwarePaginator
    {
        return QuizHistory::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function leaderboard(string $period = 'daily', int $limit = 50, ?string $categoryId = null, ?string $roomId = null): Collection
    {
        $from = match ($period) {
            'weekly' => now()->startOfWeek(),
            'monthly' => now()->startOfMonth(),
            'all_time', 'all' => null,
            'tournament' => now()->subDays(30),
            default => now()->startOfDay(),
        };

        $query = QuizHistory::query()
            ->select([
                'user_id',
                DB::raw('SUM(score) as total_score'),
                DB::raw('SUM(CASE WHEN is_winner THEN 1 ELSE 0 END) as wins'),
                DB::raw('COUNT(*) as games'),
                DB::raw('SUM(reward_coins) as reward_coins'),
            ])
            ->groupBy('user_id')
            ->orderByDesc('total_score')
            ->limit($limit);

        if ($from !== null) {
            $query->where('created_at', '>=', $from);
        }

        if ($categoryId !== null || $roomId !== null) {
            $query->whereHas('session', function ($q) use ($categoryId, $roomId): void {
                if ($categoryId !== null) {
                    $q->where('category_id', $categoryId);
                }
                if ($roomId !== null) {
                    $q->where('room_id', $roomId);
                }
            });
        }

        return $query->get()->map(fn ($row, int $i): array => [
            'rank' => $i + 1,
            'user_id' => $row->user_id,
            'total_score' => (int) $row->total_score,
            'wins' => (int) $row->wins,
            'games' => (int) $row->games,
            'reward_coins' => (int) $row->reward_coins,
            'period' => $period,
        ]);
    }

    public function statistics(User $user, ?string $configId = null): array
    {
        $stat = QuizStatistic::query()
            ->where('user_id', $user->getKey())
            ->when($configId, fn ($q) => $q->where('config_id', $configId))
            ->orderByDesc('updated_at')
            ->first();

        if ($stat === null) {
            return [
                'games_played' => 0,
                'questions_answered' => 0,
                'correct_answers' => 0,
                'wrong_answers' => 0,
                'accuracy_percent' => 0.0,
                'average_response_ms' => 0.0,
                'highest_score' => 0,
                'win_rate' => 0.0,
                'coins_won' => 0,
                'diamonds_won' => 0,
            ];
        }

        return [
            'games_played' => (int) $stat->games_played,
            'questions_answered' => (int) $stat->questions_answered,
            'correct_answers' => (int) $stat->correct_answers,
            'wrong_answers' => (int) $stat->wrong_answers,
            'accuracy_percent' => (float) $stat->accuracy_percent,
            'average_response_ms' => (float) $stat->average_response_ms,
            'highest_score' => (int) $stat->highest_score,
            'win_rate' => (float) $stat->win_rate,
            'coins_won' => (int) $stat->coins_won,
            'diamonds_won' => (int) $stat->diamonds_won,
        ];
    }
}
