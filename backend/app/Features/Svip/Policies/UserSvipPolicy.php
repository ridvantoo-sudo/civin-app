<?php

namespace App\Features\Svip\Policies;

use App\Features\Svip\Models\UserSvip;
use App\Features\Users\Models\User;

final class UserSvipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status === 'active';
    }

    public function view(User $user, UserSvip $subscription): bool
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
