<?php

namespace App\Features\Games\Plugins\SlotMachine\Repositories\Eloquent;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GameLeaderboardEntry;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineConfig;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineJackpot;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineSession;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineSpin;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineStatistic;
use App\Features\Games\Plugins\SlotMachine\Repositories\Contracts\SlotMachineRepository;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class EloquentSlotMachineRepository implements SlotMachineRepository
{
    public function activeConfig(): ?SlotMachineConfig
    {
        $with = [
            'symbols' => fn ($q) => $q->where('status', 'active')->orderBy('sort_order'),
            'paylines' => fn ($q) => $q->where('status', 'active')->orderBy('sort_order'),
            'jackpots' => fn ($q) => $q->where('status', 'active'),
        ];

        return SlotMachineConfig::query()
            ->where('status', SlotMachineConfig::STATUS_ACTIVE)
            ->where('is_default', true)
            ->with($with)
            ->first()
            ?? SlotMachineConfig::query()
                ->where('status', SlotMachineConfig::STATUS_ACTIVE)
                ->with($with)
                ->orderByDesc('version')
                ->first();
    }

    public function findSpinByIdempotency(User $user, string $idempotencyKey): ?SlotMachineSpin
    {
        return SlotMachineSpin::query()
            ->where('user_id', $user->getKey())
            ->where('idempotency_key', $idempotencyKey)
            ->with(['config', 'play', 'rewards', 'session'])
            ->first();
    }

    public function createSpin(array $attributes): SlotMachineSpin
    {
        return SlotMachineSpin::query()->create($attributes);
    }

    public function createSession(array $attributes): SlotMachineSession
    {
        return SlotMachineSession::query()->create($attributes);
    }

    public function findActiveSession(User $user, ?string $configId = null): ?SlotMachineSession
    {
        $query = SlotMachineSession::query()
            ->where('user_id', $user->getKey())
            ->where('status', SlotMachineSession::STATUS_ACTIVE)
            ->orderByDesc('updated_at');

        if ($configId !== null) {
            $query->where('config_id', $configId);
        }

        return $query->first();
    }

    public function history(User $user, int $perPage = 30): LengthAwarePaginator
    {
        return SlotMachineSpin::query()
            ->where('user_id', $user->getKey())
            ->with(['config', 'rewards'])
            ->orderByDesc('created_at')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function leaderboard(string $period = 'daily', int $limit = 50): Collection
    {
        $limit = max(1, min($limit, 100));
        $game = Game::query()->where('slug', Game::SLUG_SLOT_MACHINE)->first();
        if ($game === null) {
            return collect();
        }

        if (in_array($period, ['jackpot', 'highest_win', 'most_spins'], true)) {
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
                    'game_slug' => $entry->game?->slug ?? Game::SLUG_SLOT_MACHINE,
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

    public function jackpots(SlotMachineConfig $config): Collection
    {
        return SlotMachineJackpot::query()
            ->where('config_id', $config->getKey())
            ->where('status', SlotMachineJackpot::STATUS_ACTIVE)
            ->orderBy('type')
            ->get();
    }

    public function findJackpot(SlotMachineConfig $config, string $type, ?string $roomId = null): ?SlotMachineJackpot
    {
        $query = SlotMachineJackpot::query()
            ->where('config_id', $config->getKey())
            ->where('type', $type)
            ->where('status', SlotMachineJackpot::STATUS_ACTIVE);

        if ($type === SlotMachineJackpot::TYPE_DAILY) {
            $query->whereDate('daily_date', now()->toDateString());
        }

        if ($type === SlotMachineJackpot::TYPE_ROOM) {
            $query->where('room_id', $roomId);
        } else {
            $query->whereNull('room_id');
        }

        return $query->first();
    }

    public function statistics(User $user, ?string $configId = null): ?SlotMachineStatistic
    {
        $query = SlotMachineStatistic::query()->where('user_id', $user->getKey());
        if ($configId !== null) {
            $query->where('config_id', $configId);
        } else {
            $query->whereNull('config_id');
        }

        return $query->first();
    }

    public function upsertStatistics(User $user, ?string $configId, array $attributes): SlotMachineStatistic
    {
        $stats = $this->statistics($user, $configId);
        if ($stats === null) {
            return SlotMachineStatistic::query()->create(array_merge($attributes, [
                'user_id' => $user->getKey(),
                'config_id' => $configId,
            ]));
        }

        $stats->forceFill($attributes)->save();

        return $stats->fresh();
    }

    public function countSpinsToday(User $user, ?string $configId = null): int
    {
        $query = SlotMachineSpin::query()
            ->where('user_id', $user->getKey())
            ->whereDate('created_at', now()->toDateString())
            ->whereIn('status', [SlotMachineSpin::STATUS_SETTLED]);

        if ($configId !== null) {
            $query->where('config_id', $configId);
        }

        return $query->count();
    }

    public function lastSpinAt(User $user, ?string $configId = null): ?Carbon
    {
        $query = SlotMachineSpin::query()
            ->where('user_id', $user->getKey())
            ->where('status', SlotMachineSpin::STATUS_SETTLED)
            ->orderByDesc('created_at');

        if ($configId !== null) {
            $query->where('config_id', $configId);
        }

        return $query->first()?->created_at;
    }

    public function freeSpinsRemaining(User $user, ?string $configId = null): int
    {
        $session = $this->findActiveSession($user, $configId);

        return (int) ($session?->free_spins_remaining ?? 0);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function specialLeaderboard(string $period, int $limit, string $gameId): Collection
    {
        $query = SlotMachineSpin::query()
            ->where('status', SlotMachineSpin::STATUS_SETTLED)
            ->with(['user.profile']);

        if ($period === 'jackpot') {
            $query->where('is_jackpot', true)->orderByDesc('jackpot_coins')->orderByDesc('created_at');
        } elseif ($period === 'highest_win') {
            $query->orderByDesc('reward_coins')->orderByDesc('created_at');
        } else {
            return SlotMachineSpin::query()
                ->selectRaw('user_id, COUNT(*) as plays, SUM(reward_coins) as coins_won, SUM(cost_coins) as coins_wagered')
                ->where('status', SlotMachineSpin::STATUS_SETTLED)
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
                        'period' => 'most_spins',
                    ];
                });
        }

        return $query->limit($limit)->get()->values()->map(function (SlotMachineSpin $spin, int $index) use ($period): array {
            return [
                'rank' => $index + 1,
                'user_id' => $spin->user_id,
                'username' => $spin->user?->username,
                'display_name' => $spin->user?->profile?->display_name ?? $spin->user?->username,
                'avatar_url' => $spin->user?->profile?->avatar_url,
                'spin_id' => $spin->id,
                'reward_coins' => $spin->reward_coins,
                'jackpot_coins' => $spin->jackpot_coins,
                'is_jackpot' => $spin->is_jackpot,
                'score' => $period === 'jackpot' ? $spin->jackpot_coins : $spin->reward_coins,
                'period' => $period,
                'created_at' => $spin->created_at?->toIso8601String(),
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
