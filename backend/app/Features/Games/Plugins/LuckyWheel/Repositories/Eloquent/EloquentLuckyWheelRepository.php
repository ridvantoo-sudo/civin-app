<?php

namespace App\Features\Games\Plugins\LuckyWheel\Repositories\Eloquent;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GameLeaderboardEntry;
use App\Features\Games\Plugins\LuckyWheel\Models\LuckyWheelConfig;
use App\Features\Games\Plugins\LuckyWheel\Models\LuckyWheelSpin;
use App\Features\Games\Plugins\LuckyWheel\Repositories\Contracts\LuckyWheelRepository;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class EloquentLuckyWheelRepository implements LuckyWheelRepository
{
    public function activeConfig(): ?LuckyWheelConfig
    {
        return LuckyWheelConfig::query()
            ->where('status', LuckyWheelConfig::STATUS_ACTIVE)
            ->where('is_default', true)
            ->with(['sectors' => fn ($q) => $q->where('status', 'active')->orderBy('position')])
            ->first()
            ?? LuckyWheelConfig::query()
                ->where('status', LuckyWheelConfig::STATUS_ACTIVE)
                ->with(['sectors' => fn ($q) => $q->where('status', 'active')->orderBy('position')])
                ->orderByDesc('version')
                ->first();
    }

    public function findSpinByIdempotency(User $user, string $idempotencyKey): ?LuckyWheelSpin
    {
        return LuckyWheelSpin::query()
            ->where('user_id', $user->getKey())
            ->where('idempotency_key', $idempotencyKey)
            ->with(['sector', 'config', 'play'])
            ->first();
    }

    public function createSpin(array $attributes): LuckyWheelSpin
    {
        return LuckyWheelSpin::query()->create($attributes);
    }

    public function history(User $user, int $perPage = 30): LengthAwarePaginator
    {
        return LuckyWheelSpin::query()
            ->where('user_id', $user->getKey())
            ->with(['sector', 'config'])
            ->orderByDesc('created_at')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function leaderboard(string $period = 'daily', int $limit = 50): Collection
    {
        $period = in_array($period, ['daily', 'weekly', 'monthly', 'all'], true) ? $period : 'daily';
        $periodKey = $this->periodKey($period);
        $limit = max(1, min($limit, 100));

        $game = Game::query()->where('slug', Game::SLUG_LUCKY_WHEEL)->first();
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
            ->map(function (GameLeaderboardEntry $entry, int $index) use ($period): array {
                return [
                    'rank' => $index + 1,
                    'user_id' => $entry->user_id,
                    'username' => $entry->user?->username,
                    'display_name' => $entry->user?->profile?->display_name ?? $entry->user?->username,
                    'avatar_url' => $entry->user?->profile?->avatar_url,
                    'game_id' => $entry->game_id,
                    'game_slug' => $entry->game?->slug ?? Game::SLUG_LUCKY_WHEEL,
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

    public function countSpinsToday(User $user, ?string $configId = null): int
    {
        $query = LuckyWheelSpin::query()
            ->where('user_id', $user->getKey())
            ->whereDate('created_at', now()->toDateString())
            ->whereIn('status', [LuckyWheelSpin::STATUS_SETTLED, LuckyWheelSpin::STATUS_FULFILLMENT_PENDING]);

        if ($configId !== null) {
            $query->where('config_id', $configId);
        }

        return $query->count();
    }

    public function lastSpinAt(User $user, ?string $configId = null): ?Carbon
    {
        $query = LuckyWheelSpin::query()
            ->where('user_id', $user->getKey())
            ->whereIn('status', [LuckyWheelSpin::STATUS_SETTLED, LuckyWheelSpin::STATUS_FULFILLMENT_PENDING])
            ->orderByDesc('created_at');

        if ($configId !== null) {
            $query->where('config_id', $configId);
        }

        $spin = $query->first();

        return $spin?->created_at;
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
