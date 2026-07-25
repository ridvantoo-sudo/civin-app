<?php

namespace App\Features\MultiPkBattle\Policies;

use App\Features\MultiPkBattle\Models\MultiPkBattle;
use App\Features\Users\Models\User;

final class MultiPkBattlePolicy
{
    public function view(User $user, MultiPkBattle $battle): bool
    {
        return $battle->involves($user->getKey());
    }

    public function start(User $user, MultiPkBattle $battle): bool
    {
        return $battle->initiator_id === $user->getKey();
    }

    public function end(User $user, MultiPkBattle $battle): bool
    {
        return $battle->involves($user->getKey());
    }
}
