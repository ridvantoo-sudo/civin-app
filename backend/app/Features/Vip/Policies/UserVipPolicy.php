<?php

namespace App\Features\Vip\Policies;

use App\Features\Users\Models\User;
use App\Features\Vip\Models\UserVip;

final class UserVipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status === 'active';
    }

    public function view(User $user, UserVip $subscription): bool
    {
        return $user->getKey() === $subscription->user_id;
    }

    public function purchase(User $user): bool
    {
        return $user->status === 'active' && ! $user->is_guest;
    }

    public function upgrade(User $user): bool
    {
        return $user->status === 'active' && ! $user->is_guest;
    }
}
