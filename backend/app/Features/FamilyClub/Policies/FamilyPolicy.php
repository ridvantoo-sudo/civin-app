<?php

namespace App\Features\FamilyClub\Policies;

use App\Features\FamilyClub\Models\Family;
use App\Features\FamilyClub\Models\FamilyMember;
use App\Features\Users\Models\User;

final class FamilyPolicy
{
    public function create(User $user): bool
    {
        return $user->status === 'active' && ! $user->is_guest;
    }

    public function view(User $user, Family $family): bool
    {
        return $user->status === 'active';
    }

    public function viewRankings(User $user): bool
    {
        return $user->status === 'active';
    }

    public function join(User $user): bool
    {
        return $user->status === 'active' && ! $user->is_guest;
    }

    public function invite(User $user, Family $family): bool
    {
        return $this->manage($user, $family);
    }

    public function approve(User $user, Family $family): bool
    {
        return $this->manage($user, $family);
    }

    public function reject(User $user, Family $family): bool
    {
        return $this->manage($user, $family);
    }

    public function removeMember(User $user, Family $family): bool
    {
        return $this->manage($user, $family);
    }

    public function updateRole(User $user, Family $family): bool
    {
        return $family->isOwnedBy($user) && $user->status === 'active' && ! $user->is_guest;
    }

    public function viewMembers(User $user, Family $family): bool
    {
        return $this->isMember($user, $family) || $this->manage($user, $family) || $user->status === 'active';
    }

    public function chat(User $user, Family $family): bool
    {
        return $this->isMember($user, $family);
    }

    public function gift(User $user, Family $family): bool
    {
        return $this->isMember($user, $family);
    }

    public function wallet(User $user, Family $family): bool
    {
        return $this->isMember($user, $family);
    }

    public function missions(User $user, Family $family): bool
    {
        return $this->isMember($user, $family);
    }

    public function manage(User $user, Family $family): bool
    {
        if ($user->status !== 'active' || $user->is_guest) {
            return false;
        }

        if ($family->isOwnedBy($user)) {
            return true;
        }

        return FamilyMember::query()
            ->where('family_id', $family->getKey())
            ->where('user_id', $user->getKey())
            ->where('status', FamilyMember::STATUS_APPROVED)
            ->whereIn('role', [FamilyMember::ROLE_OWNER, FamilyMember::ROLE_ADMIN])
            ->exists();
    }

    private function isMember(User $user, Family $family): bool
    {
        if ($user->status !== 'active' || $user->is_guest) {
            return false;
        }

        return FamilyMember::query()
            ->where('family_id', $family->getKey())
            ->where('user_id', $user->getKey())
            ->where('status', FamilyMember::STATUS_APPROVED)
            ->exists();
    }
}
