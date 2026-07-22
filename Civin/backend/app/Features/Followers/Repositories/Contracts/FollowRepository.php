<?php

namespace App\Features\Followers\Repositories\Contracts;

use App\Features\Followers\Models\Follow;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface FollowRepository
{
    public function follow(User $follower, User $followed, bool $requiresApproval): Follow;

    public function unfollow(User $follower, User $followed): void;

    public function accept(User $owner, Follow $follow): Follow;

    public function reject(User $owner, Follow $follow): void;

    public function removeBetween(User $first, User $second): void;

    public function isFollowing(User $follower, User $followed): bool;

    public function followers(User $user, User $viewer, int $perPage): LengthAwarePaginator;

    public function following(User $user, User $viewer, int $perPage): LengthAwarePaginator;

    public function requests(User $user, int $perPage): LengthAwarePaginator;
}
