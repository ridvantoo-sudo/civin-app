<?php

namespace App\Features\Games\Plugins\SlotMachine\Policies;

use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineSpin;
use App\Features\Users\Models\User;

final class SlotMachinePolicy
{
    public function viewConfig(User $actor): bool
    {
        return $actor->status === User::STATUS_ACTIVE && ! $actor->is_guest;
    }

    public function play(User $actor): bool
    {
        return $actor->status === User::STATUS_ACTIVE && ! $actor->is_guest;
    }

    public function viewHistory(User $actor): bool
    {
        return $actor->status === User::STATUS_ACTIVE && ! $actor->is_guest;
    }

    public function viewStatistics(User $actor): bool
    {
        return $actor->status === User::STATUS_ACTIVE && ! $actor->is_guest;
    }

    public function viewJackpot(User $actor): bool
    {
        return $actor->status === User::STATUS_ACTIVE && ! $actor->is_guest;
    }

    public function viewOwnSpin(User $actor, SlotMachineSpin $spin): bool
    {
        return $actor->getKey() === $spin->user_id;
    }
}
