<?php

namespace App\Features\Games\Plugins\LuckyDice\Repositories\Eloquent;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GameLeaderboardEntry;
use App\Features\Games\Plugins\LuckyDice\Models\LuckyDiceBet;
use App\Features\Games\Plugins\LuckyDice\Models\LuckyDiceConfig;
use App\Features\Games\Plugins\LuckyDice\Models\LuckyDiceResult;
use App\Features\Games\Plugins\LuckyDice\Repositories\Contracts\LuckyDiceRepository;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class EloquentLuckyDiceRepository implements LuckyDiceRepository
{
    public function activeConfig(): ?LuckyDiceConfig
    {
        return LuckyDiceConfig::query()
            ->where('status', LuckyDiceConfig::STATUS_ACTIVE)
            ->where('is_default', true)
            ->first()
            ?? LuckyDiceConfig::query()
                ->where('status', LuckyDiceConfig::STATUS_ACTIVE)
                ->orderByDesc('version')
                ->first();
    }

    public function findBetByIdempotency(User $user, string $idempotencyKey): ?LuckyDiceBet
    {
        return LuckyDiceBet::query()
            ->where('user_id', $user->getKey())
            ->where('idempotency_key', $idempotencyKey)
            ->with(['result', 'config', 'play'])
            ->first();
    }

    public function createBet(array $attributes): LuckyDiceBet
    {
        return LuckyDiceBet::query()->create($attributes);
    }

    public function createResult(array $attributes): LuckyDiceResult
    {
        return LuckyDiceResult::query()->create($attributes);
    }

    public function history(User $user, int $perPage = 30): LengthAwarePaginator
    {
        return LuckyDiceBet::query()
            ->where('user_id', $user->getKey())
            ->with(['result', 'config'])
            ->orderByDesc('created_at')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function leaderboard(string $period = 'daily', int $limit = 50, string $type = 'winners'): Collection
    {
        $period = in_array($period, ['daily', 'weekly', 'monthly', 'all'], true) ? $period : 'daily';
        $type = in_array($type, ['winners', 'profit', 'plays'], true) ? $type : 'winners';
        $periodKey = $this->periodKey($period);
        $limit = max(1, min($limit, 100));

        $game = Game::query()->where('slug', Game::SLUG_LUCKY_DICE)->first();
        if ($game === null) {
            return collect();
        }

        $orderColumn = match ($type) {
            'profit' => 'coins_net',
            'plays' => 'plays',
            default => 'score',
        };

        return GameLeaderboardEntry::query()
            ->where('period', $period)
            ->where('period_key', $periodKey)
            ->where('game_id', $game->getKey())
            ->with(['user.profile', 'game'])
            ->orderByDesc($orderColumn)
            ->orderByDesc('wins')
            ->limit($limit)
            ->get()
            ->values()
            ->map(function (GameLeaderboardEntry $entry, int $index) use ($period, $type): array {
                return [
                    'rank' => $index + 1,
                    'user_id' => $entry->user_id,
                    'username' => $entry->user?->username,
                    'display_name' => $entry->user?->profile?->display_name ?? $entry->user?->username,
                    'avatar_url' => $entry->user?->profile?->avatar_url,
                    'game_id' => $entry->game_id,
                    'game_slug' => $entry->game?->slug ?? Game::SLUG_LUCKY_DICE,
                    'plays' => $entry->plays,
                    'wins' => $entry->wins,
                    'coins_wagered' => $entry->coins_wagered,
                    'coins_net' => $entry->coins_net,
                    'score' => $entry->score,
                    'period' => $period,
                    'period_key' => $entry->period_key,
                    'type' => $type,
                ];
            });
    }

    public function countBetsToday(User $user, ?string $configId = null): int
    {
        $query = LuckyDiceBet::query()
            ->where('user_id', $user->getKey())
            ->whereDate('created_at', now()->toDateString())
            ->whereIn('status', [LuckyDiceBet::STATUS_SETTLED, LuckyDiceBet::STATUS_FULFILLMENT_PENDING]);

        if ($configId !== null) {
            $query->where('config_id', $configId);
        }

        return $query->count();
    }

    public function lastBetAt(User $user, ?string $configId = null): ?Carbon
    {
        $query = LuckyDiceBet::query()
            ->where('user_id', $user->getKey())
            ->whereIn('status', [LuckyDiceBet::STATUS_SETTLED, LuckyDiceBet::STATUS_FULFILLMENT_PENDING])
            ->orderByDesc('created_at');

        if ($configId !== null) {
            $query->where('config_id', $configId);
        }

        $bet = $query->first();

        return $bet?->created_at;
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
