<?php

namespace App\Features\PkBattle\Policies;

use App\Features\PkBattle\Models\PkBattle;
use App\Features\Users\Models\User;

final class PkBattlePolicy
{
    public function view(User $user, PkBattle $battle): bool
    {
        return true;
    }

    public function start(User $user, PkBattle $battle): bool
    {
        return $battle->involvesHost($user->getKey());
    }

    public function end(User $user, PkBattle $battle): bool
    {
        return $battle->involvesHost($user->getKey());
    }
}
