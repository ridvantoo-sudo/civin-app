<?php

namespace App\Features\Followers\Services;

use App\Features\Blocking\Repositories\Contracts\BlockRepository;
use App\Features\Followers\Events\FollowRequested;
use App\Features\Followers\Events\UserFollowed;
use App\Features\Followers\Models\Follow;
use App\Features\Followers\Notifications\FollowAcceptedNotification;
use App\Features\Followers\Notifications\FollowRequestNotification;
use App\Features\Followers\Notifications\NewFollowerNotification;
use App\Features\Followers\Repositories\Contracts\FollowRepository;
use App\Features\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class FollowerService
{
    public function __construct(
        private FollowRepository $follows,
        private BlockRepository $blocks,
    ) {}

    public function follow(User $actor, User $target): Follow
    {
        $this->ensureInteractionAllowed($actor, $target);

        return DB::transaction(function () use ($actor, $target): Follow {
            $follow = $this->follows->follow(
                $actor,
                $target,
                (bool) $target->profile()->value('is_private'),
            );

            if ($follow->wasRecentlyCreated || $follow->wasChanged(['deleted_at', 'status'])) {
                if ($follow->status === 'pending') {
                    FollowRequested::dispatch($follow);
                    $target->notify(new FollowRequestNotification($follow));
                } else {
                    UserFollowed::dispatch($follow);
                    $target->notify(new NewFollowerNotification($follow));
                }
            }

            return $follow;
        });
    }

    public function unfollow(User $actor, User $target): void
    {
        DB::transaction(fn () => $this->follows->unfollow($actor, $target));
    }

    public function accept(User $actor, Follow $follow): Follow
    {
        $this->ensureOwner($actor, $follow);
        $this->ensureInteractionAllowed($actor, $follow->follower);

        return DB::transaction(function () use ($actor, $follow): Follow {
            $accepted = $this->follows->accept($actor, $follow);
            UserFollowed::dispatch($accepted);
            $accepted->follower->notify(new FollowAcceptedNotification($accepted));

            return $accepted;
        });
    }

    public function reject(User $actor, Follow $follow): void
    {
        $this->ensureOwner($actor, $follow);
        $this->follows->reject($actor, $follow);
    }

    public function followers(User $viewer, User $user, int $perPage): LengthAwarePaginator
    {
        $this->ensureListVisible($viewer, $user);

        return $this->follows->followers($user, $viewer, $perPage);
    }

    public function following(User $viewer, User $user, int $perPage): LengthAwarePaginator
    {
        $this->ensureListVisible($viewer, $user);

        return $this->follows->following($user, $viewer, $perPage);
    }

    public function requests(User $actor, int $perPage): LengthAwarePaginator
    {
        return $this->follows->requests($actor, $perPage);
    }

    private function ensureInteractionAllowed(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw ValidationException::withMessages(['user_id' => 'You cannot follow yourself.']);
        }

        if ($this->blocks->existsBetween($actor, $target)) {
            throw new AuthorizationException('Interaction is not allowed between blocked users.');
        }
    }

    private function ensureOwner(User $actor, Follow $follow): void
    {
        if ($follow->followed_id !== $actor->getKey() || $follow->status !== 'pending') {
            throw new AuthorizationException;
        }
    }

    private function ensureListVisible(User $viewer, User $target): void
    {
        if ($this->blocks->existsBetween($viewer, $target)) {
            throw new AuthorizationException('This social graph is unavailable.');
        }

        $isPrivate = (bool) $target->profile()->value('is_private');
        if (! $viewer->is($target) && $isPrivate && ! $this->follows->isFollowing($viewer, $target)) {
            throw new AuthorizationException('This account is private.');
        }
    }
}
