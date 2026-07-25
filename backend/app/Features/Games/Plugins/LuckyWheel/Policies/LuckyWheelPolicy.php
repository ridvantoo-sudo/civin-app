<?php

namespace App\Features\Games\Plugins\LuckyWheel\Policies;

use App\Features\Games\Plugins\LuckyWheel\Models\LuckyWheelSpin;
use App\Features\Users\Models\User;

final class LuckyWheelPolicy
{
    public function viewConfig(User $actor): bool
    {
        return $actor->status === User::STATUS_ACTIVE && ! $actor->is_guest;
    }

    public function spin(User $actor): bool
    {
        return $actor->status === User::STATUS_ACTIVE && ! $actor->is_guest;
    }

    public function viewHistory(User $actor): bool
    {
        return $actor->status === User::STATUS_ACTIVE && ! $actor->is_guest;
    }

    public function viewOwnSpin(User $actor, LuckyWheelSpin $spin): bool
    {
        return $actor->getKey() === $spin->user_id;
    }
}
