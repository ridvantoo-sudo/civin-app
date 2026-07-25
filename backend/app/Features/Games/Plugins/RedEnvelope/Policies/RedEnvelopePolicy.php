<?php

namespace App\Features\Games\Plugins\RedEnvelope\Policies;

use App\Features\Games\Models\RedEnvelope;
use App\Features\Users\Models\User;

final class RedEnvelopePolicy
{
    public function viewConfig(User $actor): bool
    {
        return $this->isActiveMember($actor);
    }

    public function create(User $actor): bool
    {
        return $this->isActiveMember($actor);
    }

    public function claim(User $actor): bool
    {
        return $this->isActiveMember($actor);
    }

    public function viewHistory(User $actor): bool
    {
        return $this->isActiveMember($actor);
    }

    public function viewOwnEnvelope(User $actor, RedEnvelope $envelope): bool
    {
        if ($envelope->ownerId() === $actor->getKey()) {
            return true;
        }

        return $envelope->claims()->where('user_id', $actor->getKey())->exists();
    }

    private function isActiveMember(User $actor): bool
    {
        return $actor->status === User::STATUS_ACTIVE && ! $actor->is_guest;
    }
}
