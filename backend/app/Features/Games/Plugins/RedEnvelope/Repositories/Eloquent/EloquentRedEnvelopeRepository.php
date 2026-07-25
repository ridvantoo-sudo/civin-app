<?php

namespace App\Features\Games\Plugins\RedEnvelope\Repositories\Eloquent;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GameLeaderboardEntry;
use App\Features\Games\Models\RedEnvelope;
use App\Features\Games\Models\RedEnvelopeClaim;
use App\Features\Games\Plugins\RedEnvelope\Models\RedEnvelopeConfig;
use App\Features\Games\Plugins\RedEnvelope\Repositories\Contracts\RedEnvelopeRepository;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EloquentRedEnvelopeRepository implements RedEnvelopeRepository
{
    public function activeConfig(): ?RedEnvelopeConfig
    {
        return RedEnvelopeConfig::query()
            ->where('status', RedEnvelopeConfig::STATUS_ACTIVE)
            ->where('enabled', true)
            ->where('is_default', true)
            ->first()
            ?? RedEnvelopeConfig::query()
                ->where('status', RedEnvelopeConfig::STATUS_ACTIVE)
                ->where('enabled', true)
                ->orderByDesc('version')
                ->first();
    }

    public function findEnvelope(string $envelopeId): ?RedEnvelope
    {
        return RedEnvelope::query()
            ->with(['config', 'creator', 'sender'])
            ->find($envelopeId);
    }

    public function findByIdempotency(User $creator, string $idempotencyKey): ?RedEnvelope
    {
        return RedEnvelope::query()
            ->where('sender_id', $creator->getKey())
            ->where('idempotency_key', $idempotencyKey)
            ->with(['config', 'creator'])
            ->first();
    }

    public function findClaimByIdempotency(User $user, string $idempotencyKey): ?RedEnvelopeClaim
    {
        return RedEnvelopeClaim::query()
            ->where('user_id', $user->getKey())
            ->where('idempotency_key', $idempotencyKey)
            ->with(['envelope'])
            ->first();
    }

    public function findClaimFor(RedEnvelope $envelope, User $user): ?RedEnvelopeClaim
    {
        return RedEnvelopeClaim::query()
            ->where('envelope_id', $envelope->getKey())
            ->where('user_id', $user->getKey())
            ->first();
    }

    public function createEnvelope(array $attributes): RedEnvelope
    {
        return RedEnvelope::query()->create($attributes);
    }

    public function createClaim(array $attributes): RedEnvelopeClaim
    {
        return RedEnvelopeClaim::query()->create($attributes);
    }

    public function historyCreated(User $user, int $perPage = 30): LengthAwarePaginator
    {
        return $this->baseHistoryQuery($user)
            ->where('sender_id', $user->getKey())
            ->paginate($this->perPage($perPage));
    }

    public function historyClaims(User $user, int $perPage = 30): LengthAwarePaginator
    {
        return $this->baseHistoryQuery($user)
            ->whereHas('claims', fn (Builder $query): Builder => $query->where('user_id', $user->getKey()))
            ->paginate($this->perPage($perPage));
    }

    public function history(User $user, string $type = 'all', int $perPage = 30): LengthAwarePaginator
    {
        return match ($type) {
            'created' => $this->historyCreated($user, $perPage),
            'claimed' => $this->historyClaims($user, $perPage),
            default => $this->baseHistoryQuery($user)
                ->where(function (Builder $query) use ($user): void {
                    $query->where('sender_id', $user->getKey())
                        ->orWhereHas('claims', fn (Builder $claims): Builder => $claims->where('user_id', $user->getKey()));
                })
                ->paginate($this->perPage($perPage)),
        };
    }

    public function leaderboard(string $period = 'daily', string $type = 'winners', int $limit = 50): Collection
    {
        $period = in_array($period, ['daily', 'weekly', 'monthly', 'all'], true) ? $period : 'daily';
        $type = in_array($type, ['winners', 'senders', 'receivers'], true) ? $type : 'winners';
        $periodKey = $this->periodKey($period);
        $limit = max(1, min($limit, 100));

        $game = Game::query()->where('slug', Game::SLUG_RED_ENVELOPE)->first();
        if ($game === null) {
            return collect();
        }

        $orderColumn = match ($type) {
            'senders' => 'coins_wagered',
            'receivers' => 'coins_net',
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
                'game_slug' => $entry->game?->slug ?? Game::SLUG_RED_ENVELOPE,
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

    public function openEnvelopesForRoom(?string $roomType = null, ?string $roomId = null, int $limit = 50): Collection
    {
        $query = RedEnvelope::query()
            ->where('status', RedEnvelope::STATUS_OPEN)
            ->where(function (Builder $builder): void {
                $builder->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('created_at')
            ->limit(max(1, min($limit, 100)));

        if ($roomType === null && $roomId === null) {
            $query->whereNull('room_id');
        }

        if ($roomType !== null) {
            $query->where('room_type', $roomType);
        }

        if ($roomId !== null) {
            $query->where('room_id', $roomId);
        }

        return $query->get();
    }

    public function countCreatedToday(User $user, ?string $configId = null): int
    {
        $query = RedEnvelope::query()
            ->where('sender_id', $user->getKey())
            ->whereDate('created_at', now()->toDateString());

        if ($configId !== null) {
            $query->where('config_id', $configId);
        }

        return $query->count();
    }

    public function dueForExpiry(int $limit = 100): Collection
    {
        return RedEnvelope::query()
            ->where('status', RedEnvelope::STATUS_OPEN)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->limit(max(1, min($limit, 500)))
            ->get();
    }

    public function claimedTotal(RedEnvelope $envelope): int
    {
        return (int) RedEnvelopeClaim::query()
            ->where('envelope_id', $envelope->getKey())
            ->sum('reward_amount');
    }

    private function baseHistoryQuery(User $user): Builder
    {
        return RedEnvelope::query()
            ->with([
                'config',
                'claims' => fn ($query) => $query->where('user_id', $user->getKey()),
            ])
            ->withCount('claims')
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
