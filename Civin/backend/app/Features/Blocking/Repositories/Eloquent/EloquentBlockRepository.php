<?php

namespace App\Features\Blocking\Repositories\Eloquent;

use App\Features\Blocking\Models\Block;
use App\Features\Blocking\Repositories\Contracts\BlockRepository;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentBlockRepository implements BlockRepository
{
    public function block(User $blocker, User $blocked): Block
    {
        $block = Block::query()->withTrashed()->firstOrNew([
            'blocker_id' => $blocker->getKey(),
            'blocked_id' => $blocked->getKey(),
        ]);
        $block->deleted_at = null;
        $block->save();

        return $block->load('blocked.profile', 'blocked.socialStatus');
    }

    public function unblock(User $blocker, User $blocked): void
    {
        Block::query()
            ->whereBelongsTo($blocker, 'blocker')
            ->whereBelongsTo($blocked, 'blocked')
            ->delete();
    }

    public function existsBetween(User|string $first, User|string $second): bool
    {
        $firstId = $first instanceof User ? $first->getKey() : $first;
        $secondId = $second instanceof User ? $second->getKey() : $second;

        return Block::query()
            ->where(fn ($query) => $query
                ->where('blocker_id', $firstId)
                ->where('blocked_id', $secondId))
            ->orWhere(fn ($query) => $query
                ->where('blocker_id', $secondId)
                ->where('blocked_id', $firstId))
            ->exists();
    }

    public function blockedBy(User $user, int $perPage): LengthAwarePaginator
    {
        return Block::query()
            ->whereBelongsTo($user, 'blocker')
            ->with('blocked.profile.country', 'blocked.socialStatus')
            ->latest()
            ->paginate($perPage);
    }
}
