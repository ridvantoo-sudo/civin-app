<?php

namespace App\Features\Couple\Policies;

use App\Features\Couple\Models\Couple;
use App\Features\Couple\Models\CoupleProposal;
use App\Features\Users\Models\User;

final class CouplePolicy
{
    public function propose(User $user): bool
    {
        return $user->status === 'active' && ! $user->is_guest;
    }

    public function view(User $user, Couple $couple): bool
    {
        return $user->status === 'active';
    }

    public function viewRankings(User $user): bool
    {
        return $user->status === 'active';
    }

    public function update(User $user, Couple $couple): bool
    {
        return $this->isPartner($user, $couple);
    }

    public function end(User $user, Couple $couple): bool
    {
        return $this->isPartner($user, $couple);
    }

    public function gift(User $user, Couple $couple): bool
    {
        return $this->isPartner($user, $couple);
    }

    public function tasks(User $user, Couple $couple): bool
    {
        return $this->isPartner($user, $couple);
    }

    public function anniversary(User $user, Couple $couple): bool
    {
        return $this->isPartner($user, $couple);
    }

    public function acceptProposal(User $user, CoupleProposal $proposal): bool
    {
        return $user->status === 'active'
            && ! $user->is_guest
            && $proposal->to_user_id === $user->getKey();
    }

    public function rejectProposal(User $user, CoupleProposal $proposal): bool
    {
        return $this->acceptProposal($user, $proposal);
    }

    public function cancelProposal(User $user, CoupleProposal $proposal): bool
    {
        return $user->status === 'active'
            && ! $user->is_guest
            && $proposal->from_user_id === $user->getKey();
    }

    private function isPartner(User $user, Couple $couple): bool
    {
        return $user->status === 'active'
            && ! $user->is_guest
            && $couple->isActive()
            && $couple->includesUser($user);
    }
}
