<?php

namespace App\Features\Games\Plugins\LuckyNumber\Policies;

use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberTicket;
use App\Features\Users\Models\User;

final class LuckyNumberPolicy
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

    public function viewDraws(User $actor): bool
    {
        return $actor->status === User::STATUS_ACTIVE && ! $actor->is_guest;
    }

    public function viewOwnTicket(User $actor, LuckyNumberTicket $ticket): bool
    {
        return $actor->getKey() === $ticket->user_id;
    }
}
