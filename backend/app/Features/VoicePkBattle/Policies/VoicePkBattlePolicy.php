<?php

namespace App\Features\VoicePkBattle\Policies;

use App\Features\Users\Models\User;
use App\Features\VoicePkBattle\Models\VoicePkBattle;

final class VoicePkBattlePolicy
{
    public function view(User $user, VoicePkBattle $battle): bool
    {
        return $battle->involves($user->getKey());
    }

    public function start(User $user, VoicePkBattle $battle): bool
    {
        return $battle->initiator_id === $user->getKey();
    }

    public function end(User $user, VoicePkBattle $battle): bool
    {
        return $battle->involves($user->getKey());
    }
}
