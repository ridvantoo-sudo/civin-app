<?php

namespace App\Features\Games\Plugins\Quiz\Policies;

use App\Features\Games\Plugins\Quiz\Models\QuizSession;
use App\Features\Users\Models\User;

final class QuizPolicy
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

    public function start(User $actor, QuizSession $session): bool
    {
        return $this->isActiveMember($actor) && (string) $session->host_id === (string) $actor->getKey();
    }

    public function answer(User $actor, QuizSession $session): bool
    {
        if (! $this->isActiveMember($actor)) {
            return false;
        }

        return $session->players()->where('user_id', $actor->getKey())->exists();
    }

    public function viewHistory(User $actor): bool
    {
        return $this->isActiveMember($actor);
    }

    public function viewStatistics(User $actor): bool
    {
        return $this->isActiveMember($actor);
    }

    public function viewOwnSession(User $actor, QuizSession $session): bool
    {
        if ((string) $session->host_id === (string) $actor->getKey()) {
            return true;
        }

        return $session->players()->where('user_id', $actor->getKey())->exists();
    }

    private function isActiveMember(User $actor): bool
    {
        return $actor->status === User::STATUS_ACTIVE && ! $actor->is_guest;
    }
}
