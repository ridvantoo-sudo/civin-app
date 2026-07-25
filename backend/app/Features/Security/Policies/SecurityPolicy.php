<?php

namespace App\Features\Security\Policies;

use App\Features\Security\Support\SecurityPermission;
use App\Features\Users\Models\User;

/**
 * Authorization for the security surface.
 *
 * Every player may inspect their own posture; only privileged staff may see
 * the platform-wide picture or change enforcement.
 */
final class SecurityPolicy
{
    public function viewOwn(User $user): bool
    {
        return $user->status === User::STATUS_ACTIVE;
    }

    public function viewAny(User $user): bool
    {
        return SecurityPermission::allows($user, SecurityPermission::VIEW_SECURITY);
    }

    public function viewDashboard(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function manageRules(User $user): bool
    {
        return SecurityPermission::allows($user, SecurityPermission::MANAGE_SECURITY_RULES);
    }

    public function review(User $user): bool
    {
        return SecurityPermission::allows($user, SecurityPermission::REVIEW_SECURITY_CASES);
    }

    public function manageBans(User $user): bool
    {
        return SecurityPermission::allows($user, SecurityPermission::MANAGE_SECURITY_BANS);
    }
}
