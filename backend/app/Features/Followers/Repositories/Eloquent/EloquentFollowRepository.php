<?php

namespace App\Features\Followers\Repositories\Eloquent;

use App\Features\Followers\Models\Follow;
use App\Features\Followers\Repositories\Contracts\FollowRepository;
use App\Features\Profiles\Models\Profile;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class EloquentFollowRepository implements FollowRepository
{
    public function follow(User $follower, User $followed, bool $requiresApproval): Follow
    {
        $follow = Follow::query()->withTrashed()->firstOrNew([
            'follower_id' => $follower->getKey(),
            'followed_id' => $followed->getKey(),
        ]);

        if ($follow->exists && ! $follow->trashed()) {
            return $follow->load('follower.profile', 'followed.profile');
        }

        $follow->deleted_at = null;
        $follow->status = $requiresApproval ? 'pending' : 'accepted';
        $follow->accepted_at = $requiresApproval ? null : now();
        $follow->save();

        if (! $requiresApproval) {
            $this->incrementCounters($follower, $followed);
        }

        return $follow->load('follower.profile', 'followed.profile');
    }

    public function unfollow(User $follower, User $followed): void
    {
        $follow = Follow::query()
            ->whereBelongsTo($follower, 'follower')
            ->whereBelongsTo($followed, 'followed')
            ->lockForUpdate()
            ->first();

        if ($follow === null) {
            return;
        }

        if ($follow->status === 'accepted') {
            $this->decrementCounters($follower, $followed);
        }

        $follow->delete();
    }

    public function accept(User $owner, Follow $follow): Follow
    {
        $follow = Follow::query()->lockForUpdate()->findOrFail($follow->getKey());

        if ($follow->status === 'pending') {
            $follow->update(['status' => 'accepted', 'accepted_at' => now()]);
            $this->incrementCounters($follow->follower, $owner);
        }

        return $follow->fresh()->load('follower.profile', 'followed.profile');
    }

    public function reject(User $owner, Follow $follow): void
    {
        Follow::query()
            ->whereKey($follow->getKey())
            ->where('followed_id', $owner->getKey())
            ->where('status', 'pending')
            ->delete();
    }

    public function removeBetween(User $first, User $second): void
    {
        Follow::query()
            ->where(fn (Builder $query) => $query
                ->where('follower_id', $first->getKey())
                ->where('followed_id', $second->getKey()))
            ->orWhere(fn (Builder $query) => $query
                ->where('follower_id', $second->getKey())
                ->where('followed_id', $first->getKey()))
            ->lockForUpdate()
            ->get()
            ->each(function (Follow $follow): void {
                if ($follow->status === 'accepted') {
                    $this->decrementCounters($follow->follower, $follow->followed);
                }
                $follow->delete();
            });
    }

    public function isFollowing(User $follower, User $followed): bool
    {
        return Follow::query()
            ->whereBelongsTo($follower, 'follower')
            ->whereBelongsTo($followed, 'followed')
            ->where('status', 'accepted')
            ->exists();
    }

    public function followers(User $user, User $viewer, int $perPage): LengthAwarePaginator
    {
        $query = $this->acceptedQuery('followed_id', $user);
        $this->excludeBlockedUsers($query, $viewer, 'followers.follower_id');

        return $query
            ->with('follower.profile.country', 'follower.socialStatus')
            ->latest('accepted_at')
            ->paginate($perPage);
    }

    public function following(User $user, User $viewer, int $perPage): LengthAwarePaginator
    {
        $query = $this->acceptedQuery('follower_id', $user);
        $this->excludeBlockedUsers($query, $viewer, 'followers.followed_id');

        return $query
            ->with('followed.profile.country', 'followed.socialStatus')
            ->latest('accepted_at')
            ->paginate($perPage);
    }

    public function requests(User $user, int $perPage): LengthAwarePaginator
    {
        return Follow::query()
            ->whereBelongsTo($user, 'followed')
            ->where('status', 'pending')
            ->with('follower.profile.country', 'follower.socialStatus')
            ->latest()
            ->paginate($perPage);
    }

    private function acceptedQuery(string $foreignKey, User $user): Builder
    {
        return Follow::query()
            ->where($foreignKey, $user->getKey())
            ->where('status', 'accepted');
    }

    private function excludeBlockedUsers(Builder $query, User $viewer, string $relatedColumn): void
    {
        $query->whereNotExists(fn ($blocks) => $blocks
            ->selectRaw('1')
            ->from('blocks')
            ->whereNull('blocks.deleted_at')
            ->where(fn ($blocked) => $blocked
                ->where(fn ($pair) => $pair
                    ->where('blocks.blocker_id', $viewer->getKey())
                    ->whereColumn('blocks.blocked_id', $relatedColumn))
                ->orWhere(fn ($pair) => $pair
                    ->whereColumn('blocks.blocker_id', $relatedColumn)
                    ->where('blocks.blocked_id', $viewer->getKey()))));
    }

    private function incrementCounters(User $follower, User $followed): void
    {
        Profile::query()->where('user_id', $follower->getKey())->increment('following_count');
        Profile::query()->where('user_id', $followed->getKey())->increment('followers_count');
    }

    private function decrementCounters(User $follower, User $followed): void
    {
        Profile::query()
            ->where('user_id', $follower->getKey())
            ->where('following_count', '>', 0)
            ->decrement('following_count');
        Profile::query()
            ->where('user_id', $followed->getKey())
            ->where('followers_count', '>', 0)
            ->decrement('followers_count');
    }
}
