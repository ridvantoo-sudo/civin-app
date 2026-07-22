<?php

namespace App\Features\Agency\Policies;

use App\Features\Agency\Models\Agency;
use App\Features\Users\Models\User;

final class AgencyPolicy
{
    public function create(User $user): bool
    {
        return $user->status === 'active' && ! $user->is_guest;
    }

    public function view(User $user, Agency $agency): bool
    {
        return $user->status === 'active';
    }

    public function apply(User $user, Agency $agency): bool
    {
        return $user->status === 'active'
            && ! $user->is_guest
            && ! $agency->isOwnedBy($user);
    }

    public function approve(User $user, Agency $agency): bool
    {
        return $this->manage($user, $agency);
    }

    public function reject(User $user, Agency $agency): bool
    {
        return $this->manage($user, $agency);
    }

    public function removeMember(User $user, Agency $agency): bool
    {
        return $this->manage($user, $agency);
    }

    public function viewHosts(User $user, Agency $agency): bool
    {
        return $this->manage($user, $agency);
    }

    public function viewEarnings(User $user, Agency $agency): bool
    {
        return $this->manage($user, $agency);
    }

    public function manage(User $user, Agency $agency): bool
    {
        return $agency->isOwnedBy($user) && $user->status === 'active' && ! $user->is_guest;
    }
}
