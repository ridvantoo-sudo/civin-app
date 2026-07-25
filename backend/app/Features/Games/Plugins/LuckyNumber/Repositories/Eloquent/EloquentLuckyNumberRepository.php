<?php

namespace App\Features\Games\Plugins\LuckyNumber\Repositories\Eloquent;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GameLeaderboardEntry;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberConfig;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberDraw;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberJackpot;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberStatistic;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberTicket;
use App\Features\Games\Plugins\LuckyNumber\Repositories\Contracts\LuckyNumberRepository;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class EloquentLuckyNumberRepository implements LuckyNumberRepository
{
    public function activeConfig(): ?LuckyNumberConfig
    {
        $with = [
            'jackpots' => fn ($q) => $q->where('status', LuckyNumberJackpot::STATUS_ACTIVE),
        ];

        return LuckyNumberConfig::query()
            ->where('status', LuckyNumberConfig::STATUS_ACTIVE)
            ->where('is_default', true)
            ->with($with)
            ->first()
            ?? LuckyNumberConfig::query()
                ->where('status', LuckyNumberConfig::STATUS_ACTIVE)
                ->with($with)
                ->orderByDesc('version')
                ->first();
    }

    public function findTicketByIdempotency(User $user, string $idempotencyKey): ?LuckyNumberTicket
    {
        return LuckyNumberTicket::query()
            ->where('user_id', $user->getKey())
            ->where('idempotency_key', $idempotencyKey)
            ->with(['config', 'draw', 'play', 'rewards', 'result'])
            ->first();
    }

    public function createTicket(array $attributes): LuckyNumberTicket
    {
        return LuckyNumberTicket::query()->create($attributes);
    }

    public function createDraw(array $attributes): LuckyNumberDraw
    {
        return LuckyNumberDraw::query()->create($attributes);
    }

    public function findPendingScheduledDraws(int $limit = 50): Collection
    {
        return LuckyNumberDraw::query()
            ->where('status', LuckyNumberDraw::STATUS_PENDING)
            ->where('draw_type', LuckyNumberDraw::TYPE_SCHEDULED)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit(max(1, min($limit, 200)))
            ->get();
    }

    public function history(User $user, int $perPage = 30): LengthAwarePaginator
    {
        return LuckyNumberTicket::query()
            ->where('user_id', $user->getKey())
            ->with(['config', 'draw', 'rewards', 'result'])
            ->orderByDesc('created_at')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function leaderboard(string $period = 'daily', int $limit = 50): Collection
    {
        $limit = max(1, min($limit, 100));
        $game = Game::query()->where('slug', Game::SLUG_LUCKY_NUMBER)->first();
        if ($game === null) {
            return collect();
        }

        if (in_array($period, ['jackpot', 'highest_win', 'most_tickets', 'win_rate'], true)) {
            return $this->specialLeaderboard($period, $limit, $game->getKey());
        }

        $period = in_array($period, ['daily', 'weekly', 'monthly', 'all'], true) ? $period : 'daily';
        $periodKey = $this->periodKey($period);

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
            ->map(function (GameLeaderboardEntry $entry, int $index) use ($period): array {
                return [
                    'rank' => $index + 1,
                    'user_id' => $entry->user_id,
                    'username' => $entry->user?->username,
                    'display_name' => $entry->user?->profile?->display_name ?? $entry->user?->username,
                    'avatar_url' => $entry->user?->profile?->avatar_url,
                    'game_id' => $entry->game_id,
                    'game_slug' => $entry->game?->slug ?? Game::SLUG_LUCKY_NUMBER,
                    'plays' => $entry->plays,
                    'wins' => $entry->wins,
                    'coins_wagered' => $entry->coins_wagered,
                    'coins_net' => $entry->coins_net,
                    'score' => $entry->score,
                    'period' => $period,
                    'period_key' => $entry->period_key,
                ];
            });
    }

    public function jackpots(LuckyNumberConfig $config): Collection
    {
        return LuckyNumberJackpot::query()
            ->where('config_id', $config->getKey())
            ->where('status', LuckyNumberJackpot::STATUS_ACTIVE)
            ->orderBy('type')
            ->get();
    }

    public function findJackpot(LuckyNumberConfig $config, string $type, ?string $roomId = null): ?LuckyNumberJackpot
    {
        $query = LuckyNumberJackpot::query()
            ->where('config_id', $config->getKey())
            ->where('type', $type)
            ->where('status', LuckyNumberJackpot::STATUS_ACTIVE);

        if ($type === LuckyNumberJackpot::TYPE_DAILY) {
            $query->whereDate('daily_date', now()->toDateString());
        }

        if ($type === LuckyNumberJackpot::TYPE_ROOM) {
            $query->where('room_id', $roomId);
        } else {
            $query->whereNull('room_id');
        }

        return $query->first();
    }

    public function statistics(User $user, ?string $configId = null): ?LuckyNumberStatistic
    {
        $query = LuckyNumberStatistic::query()->where('user_id', $user->getKey());
        if ($configId !== null) {
            $query->where('config_id', $configId);
        } else {
            $query->whereNull('config_id');
        }

        return $query->first();
    }

    public function upsertStatistics(User $user, ?string $configId, array $attributes): LuckyNumberStatistic
    {
        $stats = $this->statistics($user, $configId);
        if ($stats === null) {
            return LuckyNumberStatistic::query()->create(array_merge($attributes, [
                'user_id' => $user->getKey(),
                'config_id' => $configId,
            ]));
        }

        $stats->forceFill($attributes)->save();

        return $stats->fresh();
    }

    public function countTicketsToday(User $user, ?string $configId = null): int
    {
        $query = LuckyNumberTicket::query()
            ->where('user_id', $user->getKey())
            ->whereDate('created_at', now()->toDateString())
            ->whereIn('status', [LuckyNumberTicket::STATUS_SETTLED]);

        if ($configId !== null) {
            $query->where('config_id', $configId);
        }

        return $query->count();
    }

    public function lastTicketAt(User $user, ?string $configId = null): ?Carbon
    {
        return $this->lastTicket($user, $configId)?->created_at;
    }

    public function lastTicket(User $user, ?string $configId = null): ?LuckyNumberTicket
    {
        $query = LuckyNumberTicket::query()
            ->where('user_id', $user->getKey())
            ->where('status', LuckyNumberTicket::STATUS_SETTLED)
            ->orderByDesc('created_at');

        if ($configId !== null) {
            $query->where('config_id', $configId);
        }

        return $query->first();
    }

    public function draws(?User $user, int $perPage = 30): LengthAwarePaginator
    {
        $query = LuckyNumberDraw::query()
            ->where('status', LuckyNumberDraw::STATUS_FINISHED)
            ->with(['config', 'results'])
            ->orderByDesc('finished_at')
            ->orderByDesc('created_at');

        if ($user !== null) {
            $query->whereHas('tickets', fn ($q) => $q->where('user_id', $user->getKey()));
        }

        return $query->paginate(max(1, min($perPage, 100)));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function specialLeaderboard(string $period, int $limit, string $gameId): Collection
    {
        if ($period === 'most_tickets') {
            return LuckyNumberTicket::query()
                ->selectRaw('user_id, COUNT(*) as plays, SUM(reward_coins) as coins_won, SUM(cost_coins) as coins_wagered')
                ->where('status', LuckyNumberTicket::STATUS_SETTLED)
                ->groupBy('user_id')
                ->orderByDesc('plays')
                ->limit($limit)
                ->get()
                ->values()
                ->map(function ($row, int $index): array {
                    $user = User::query()->with('profile')->find($row->user_id);

                    return [
                        'rank' => $index + 1,
                        'user_id' => $row->user_id,
                        'username' => $user?->username,
                        'display_name' => $user?->profile?->display_name ?? $user?->username,
                        'avatar_url' => $user?->profile?->avatar_url,
                        'plays' => (int) $row->plays,
                        'coins_wagered' => (int) $row->coins_wagered,
                        'coins_won' => (int) $row->coins_won,
                        'score' => (int) $row->plays,
                        'period' => 'most_tickets',
                    ];
                });
        }

        if ($period === 'win_rate') {
            return LuckyNumberTicket::query()
                ->selectRaw('user_id, COUNT(*) as plays, SUM(CASE WHEN is_win = 1 THEN 1 ELSE 0 END) as wins, SUM(reward_coins) as coins_won, SUM(cost_coins) as coins_wagered')
                ->where('status', LuckyNumberTicket::STATUS_SETTLED)
                ->groupBy('user_id')
                ->havingRaw('COUNT(*) >= 5')
                ->orderByRaw('(SUM(CASE WHEN is_win = 1 THEN 1 ELSE 0 END) / COUNT(*)) DESC')
                ->orderByDesc('plays')
                ->limit($limit)
                ->get()
                ->values()
                ->map(function ($row, int $index): array {
                    $user = User::query()->with('profile')->find($row->user_id);
                    $plays = (int) $row->plays;
                    $wins = (int) $row->wins;
                    $winRate = $plays > 0 ? round(($wins / $plays) * 100, 2) : 0.0;

                    return [
                        'rank' => $index + 1,
                        'user_id' => $row->user_id,
                        'username' => $user?->username,
                        'display_name' => $user?->profile?->display_name ?? $user?->username,
                        'avatar_url' => $user?->profile?->avatar_url,
                        'plays' => $plays,
                        'wins' => $wins,
                        'win_rate' => $winRate,
                        'coins_wagered' => (int) $row->coins_wagered,
                        'coins_won' => (int) $row->coins_won,
                        'score' => $winRate,
                        'period' => 'win_rate',
                    ];
                });
        }

        $query = LuckyNumberTicket::query()
            ->where('status', LuckyNumberTicket::STATUS_SETTLED)
            ->with(['user.profile']);

        if ($period === 'jackpot') {
            $query->where('is_jackpot', true)->orderByDesc('jackpot_coins')->orderByDesc('created_at');
        } else {
            $query->orderByDesc('reward_coins')->orderByDesc('created_at');
        }

        return $query->limit($limit)->get()->values()->map(function (LuckyNumberTicket $ticket, int $index) use ($period): array {
            return [
                'rank' => $index + 1,
                'user_id' => $ticket->user_id,
                'username' => $ticket->user?->username,
                'display_name' => $ticket->user?->profile?->display_name ?? $ticket->user?->username,
                'avatar_url' => $ticket->user?->profile?->avatar_url,
                'ticket_id' => $ticket->id,
                'reward_coins' => $ticket->reward_coins,
                'jackpot_coins' => $ticket->jackpot_coins,
                'is_jackpot' => $ticket->is_jackpot,
                'score' => $period === 'jackpot' ? $ticket->jackpot_coins : $ticket->reward_coins,
                'period' => $period,
                'created_at' => $ticket->created_at?->toIso8601String(),
            ];
        });
    }

    private function periodKey(string $period): string
    {
        return match ($period) {
            'weekly' => now()->format('o-\WW'),
            'monthly' => now()->format('Y-m'),
            'all' => 'all',
            default => now()->toDateString(),
        };
    }
}
