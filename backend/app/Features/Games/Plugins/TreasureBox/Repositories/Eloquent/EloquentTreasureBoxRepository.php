<?php

namespace App\Features\Games\Plugins\TreasureBox\Repositories\Eloquent;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GameLeaderboardEntry;
use App\Features\Games\Models\TreasureBox;
use App\Features\Games\Models\TreasureBoxParticipant;
use App\Features\Games\Models\TreasureBoxReward;
use App\Features\Games\Models\TreasureBoxStatistic;
use App\Features\Games\Models\TreasureBoxWinner;
use App\Features\Games\Plugins\TreasureBox\Models\TreasureBoxConfig;
use App\Features\Games\Plugins\TreasureBox\Repositories\Contracts\TreasureBoxRepository;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EloquentTreasureBoxRepository implements TreasureBoxRepository
{
    public function activeConfig(): ?TreasureBoxConfig
    {
        return TreasureBoxConfig::query()
            ->where('status', TreasureBoxConfig::STATUS_ACTIVE)
            ->where('enabled', true)
            ->where('is_default', true)
            ->first()
            ?? TreasureBoxConfig::query()
                ->where('status', TreasureBoxConfig::STATUS_ACTIVE)
                ->where('enabled', true)
                ->orderByDesc('version')
                ->first();
    }

    public function findTreasure(string $treasureBoxId): ?TreasureBox
    {
        return TreasureBox::query()
            ->with(['config', 'creator', 'rewards', 'winners'])
            ->find($treasureBoxId);
    }

    public function findByIdempotency(User $creator, string $idempotencyKey): ?TreasureBox
    {
        return TreasureBox::query()
            ->where('creator_id', $creator->getKey())
            ->where('idempotency_key', $idempotencyKey)
            ->with(['config', 'creator', 'rewards'])
            ->first();
    }

    public function findParticipantByIdempotency(User $user, string $idempotencyKey): ?TreasureBoxParticipant
    {
        return TreasureBoxParticipant::query()
            ->where('user_id', $user->getKey())
            ->where('idempotency_key', $idempotencyKey)
            ->with(['treasureBox'])
            ->first();
    }

    public function findParticipantFor(TreasureBox $treasure, User $user): ?TreasureBoxParticipant
    {
        return TreasureBoxParticipant::query()
            ->where('treasure_box_id', $treasure->getKey())
            ->where('user_id', $user->getKey())
            ->first();
    }

    public function findWinnerByIdempotency(User $user, string $idempotencyKey): ?TreasureBoxWinner
    {
        return TreasureBoxWinner::query()
            ->where('user_id', $user->getKey())
            ->where('idempotency_key', $idempotencyKey)
            ->with(['treasureBox'])
            ->first();
    }

    public function createTreasure(array $attributes): TreasureBox
    {
        return TreasureBox::query()->create($attributes);
    }

    public function createParticipant(array $attributes): TreasureBoxParticipant
    {
        return TreasureBoxParticipant::query()->create($attributes);
    }

    public function createReward(array $attributes): TreasureBoxReward
    {
        return TreasureBoxReward::query()->create($attributes);
    }

    public function createWinner(array $attributes): TreasureBoxWinner
    {
        return TreasureBoxWinner::query()->create($attributes);
    }

    public function history(User $user, string $type = 'all', int $perPage = 30): LengthAwarePaginator
    {
        return match ($type) {
            'created' => $this->baseHistoryQuery($user)
                ->where('creator_id', $user->getKey())
                ->paginate($this->perPage($perPage)),
            'joined' => $this->baseHistoryQuery($user)
                ->whereHas('participants', fn (Builder $query): Builder => $query->where('user_id', $user->getKey()))
                ->paginate($this->perPage($perPage)),
            'won' => $this->baseHistoryQuery($user)
                ->whereHas('winners', fn (Builder $query): Builder => $query->where('user_id', $user->getKey()))
                ->paginate($this->perPage($perPage)),
            default => $this->baseHistoryQuery($user)
                ->where(function (Builder $query) use ($user): void {
                    $query->where('creator_id', $user->getKey())
                        ->orWhereHas('participants', fn (Builder $p): Builder => $p->where('user_id', $user->getKey()))
                        ->orWhereHas('winners', fn (Builder $w): Builder => $w->where('user_id', $user->getKey()));
                })
                ->paginate($this->perPage($perPage)),
        };
    }

    public function leaderboard(string $period = 'daily', string $type = 'winners', int $limit = 50): Collection
    {
        $period = in_array($period, ['daily', 'weekly', 'monthly', 'all'], true) ? $period : 'daily';
        $type = in_array($type, ['winners', 'hosts', 'hunters'], true) ? $type : 'winners';
        $periodKey = $this->periodKey($period);
        $limit = max(1, min($limit, 100));

        $game = Game::query()->where('slug', Game::SLUG_TREASURE_BOX)->first();
        if ($game === null) {
            return collect();
        }

        $orderColumn = match ($type) {
            'hosts' => 'coins_wagered',
            'hunters' => 'plays',
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
            ->map(fn (GameLeaderboardEntry $entry, int $index): array => [
                'rank' => $index + 1,
                'user_id' => $entry->user_id,
                'username' => $entry->user?->username,
                'display_name' => $entry->user?->profile?->display_name ?? $entry->user?->username,
                'avatar_url' => $entry->user?->profile?->avatar_url,
                'game_id' => $entry->game_id,
                'game_slug' => $entry->game?->slug ?? Game::SLUG_TREASURE_BOX,
                'plays' => $entry->plays,
                'wins' => $entry->wins,
                'coins_wagered' => $entry->coins_wagered,
                'coins_net' => $entry->coins_net,
                'score' => $entry->score,
                'period' => $period,
                'period_key' => $entry->period_key,
                'type' => $type,
            ]);
    }

    public function statisticsFor(User $user): TreasureBoxStatistic
    {
        return TreasureBoxStatistic::query()->firstOrCreate(
            ['user_id' => $user->getKey()],
            [
                'scope' => TreasureBoxStatistic::SCOPE_USER,
                'treasures_created' => 0,
                'treasures_joined' => 0,
                'treasures_won' => 0,
                'total_reward_amount' => 0,
                'total_spent_amount' => 0,
                'jackpots_won' => 0,
                'average_join_seconds' => 0,
            ],
        );
    }

    public function countCreatedToday(User $user, ?string $configId = null): int
    {
        $query = TreasureBox::query()
            ->where('creator_id', $user->getKey())
            ->whereDate('created_at', now()->toDateString());

        if ($configId !== null) {
            $query->where('config_id', $configId);
        }

        return $query->count();
    }

    public function dueForScheduleStart(int $limit = 100): Collection
    {
        return TreasureBox::query()
            ->where('status', TreasureBox::STATUS_SCHEDULED)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit(max(1, min($limit, 500)))
            ->get();
    }

    public function dueForDraw(int $limit = 100): Collection
    {
        return TreasureBox::query()
            ->whereIn('status', [TreasureBox::STATUS_OPEN, TreasureBox::STATUS_COUNTDOWN])
            ->whereNotNull('draw_at')
            ->where('draw_at', '<=', now())
            ->orderBy('draw_at')
            ->limit(max(1, min($limit, 500)))
            ->get();
    }

    public function dueForExpiry(int $limit = 100): Collection
    {
        return TreasureBox::query()
            ->whereIn('status', [TreasureBox::STATUS_OPEN, TreasureBox::STATUS_COUNTDOWN, TreasureBox::STATUS_SCHEDULED])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->limit(max(1, min($limit, 500)))
            ->get();
    }

    public function participantsFor(TreasureBox $treasure): Collection
    {
        return TreasureBoxParticipant::query()
            ->where('treasure_box_id', $treasure->getKey())
            ->orderBy('joined_at')
            ->get();
    }

    private function baseHistoryQuery(User $user): Builder
    {
        return TreasureBox::query()
            ->with([
                'config',
                'participants' => fn ($query) => $query->where('user_id', $user->getKey()),
                'winners' => fn ($query) => $query->where('user_id', $user->getKey()),
            ])
            ->withCount(['participants', 'winners'])
            ->orderByDesc('created_at');
    }

    private function perPage(int $perPage): int
    {
        return max(1, min($perPage, 100));
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
