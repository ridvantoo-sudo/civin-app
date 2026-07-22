<?php

namespace App\Features\Followers\Policies;

use App\Features\Followers\Models\Follow;
use App\Features\Users\Models\User;

final class FollowPolicy
{
    public function respond(User $user, Follow $follow): bool
    {
        return $follow->followed_id === $user->getKey() && $follow->status === 'pending';
    }
}
