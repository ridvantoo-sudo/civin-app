<?php

namespace App\Features\Games\Plugins\TreasureBox\Policies;

use App\Features\Games\Models\TreasureBox;
use App\Features\Users\Models\User;

final class TreasureBoxPolicy
{
    public function viewConfig(User $actor): bool
    {
        return $this->isActiveMember($actor);
    }

    public function create(User $actor): bool
    {
        return $this->isActiveMember($actor);
    }

    public function join(User $actor): bool
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

    public function viewOwnTreasure(User $actor, TreasureBox $treasure): bool
    {
        if ($treasure->ownerId() === $actor->getKey()) {
            return true;
        }

        return $treasure->participants()->where('user_id', $actor->getKey())->exists()
            || $treasure->winners()->where('user_id', $actor->getKey())->exists();
    }

    private function isActiveMember(User $actor): bool
    {
        return $actor->status === User::STATUS_ACTIVE && ! $actor->is_guest;
    }
}
