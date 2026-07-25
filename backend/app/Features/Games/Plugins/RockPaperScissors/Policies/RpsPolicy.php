<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Policies;

use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;
use App\Features\Users\Models\User;

final class RpsPolicy
{
    public function viewConfig(User $actor): bool
    {
        return $this->isActiveMember($actor);
    }

    public function create(User $actor): bool
    {
        return $this->isActiveMember($actor);
    }

    public function accept(User $actor, RpsMatch $match): bool
    {
        if (! $this->isActiveMember($actor)) {
            return false;
        }

        if ((string) $match->challenger_id === (string) $actor->getKey()) {
            return false;
        }

        return $match->opponent_id === null
            || (string) $match->opponent_id === (string) $actor->getKey();
    }

    public function move(User $actor, RpsMatch $match): bool
    {
        return $this->isActiveMember($actor) && $match->roleFor((string) $actor->getKey()) !== null;
    }

    public function rematch(User $actor, RpsMatch $match): bool
    {
        return $this->isActiveMember($actor) && $match->roleFor((string) $actor->getKey()) !== null;
    }

    public function joinTournament(User $actor): bool
    {
        return $this->isActiveMember($actor);
    }

    public function viewHistory(User $actor): bool
    {
        return $this->isActiveMember($actor);
    }

    public function viewStatistics(User $actor): bool
    {
        return $this->isActiveMember($actor);
    }

    public function view(User $actor, RpsMatch $match): bool
    {
        if (! $this->isActiveMember($actor)) {
            return false;
        }

        return $match->roleFor((string) $actor->getKey()) !== null || $match->visibility === 'public';
    }

    private function isActiveMember(User $actor): bool
    {
        return $actor->status === User::STATUS_ACTIVE && ! $actor->is_guest;
    }
}
