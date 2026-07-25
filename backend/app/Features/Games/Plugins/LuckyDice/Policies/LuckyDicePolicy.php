<?php

namespace App\Features\Games\Plugins\LuckyDice\Policies;

use App\Features\Games\Plugins\LuckyDice\Models\LuckyDiceBet;
use App\Features\Users\Models\User;

final class LuckyDicePolicy
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

    public function viewOwnBet(User $actor, LuckyDiceBet $bet): bool
    {
        return $actor->getKey() === $bet->user_id;
    }
}
