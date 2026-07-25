<?php

namespace App\Features\Games\Plugins\Ludo\Policies;

use App\Features\Games\Plugins\Ludo\Models\LudoMatch;
use App\Features\Users\Models\User;

final class LudoPolicy
{
    public function viewConfig(User $user): bool
    {
        return $this->isActiveMember($user);
    }

    public function create(User $user): bool
    {
        return $this->isActiveMember($user);
    }

    public function join(User $user): bool
    {
        return $this->isActiveMember($user);
    }

    public function joinTournament(User $user): bool
    {
        return $this->isActiveMember($user);
    }

    public function viewHistory(User $user): bool
    {
        return $this->isActiveMember($user);
    }

    public function viewStatistics(User $user): bool
    {
        return $this->isActiveMember($user);
    }

    public function view(User $user, LudoMatch $match): bool
    {
        if (! $this->isActiveMember($user)) {
            return false;
        }

        return $match->spectators_enabled
            || $match->players()->where('user_id', $user->getKey())->exists();
    }

    public function update(User $user, LudoMatch $match): bool
    {
        return $this->isActiveMember($user)
            && $match->players()->where('user_id', $user->getKey())->where('is_spectator', false)->exists();
    }

    public function manage(User $user, LudoMatch $match): bool
    {
        return $this->isActiveMember($user)
            && (string) $match->host_id === (string) $user->getKey();
    }

    private function isActiveMember(User $user): bool
    {
        return $user->status === User::STATUS_ACTIVE && ! $user->is_guest;
    }
}
