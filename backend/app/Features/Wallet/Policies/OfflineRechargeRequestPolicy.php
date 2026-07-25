<?php

namespace App\Features\Wallet\Policies;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\OfflineRechargeRequest;

final class OfflineRechargeRequestPolicy
{
    public function create(User $user): bool
    {
        return $user->status === User::STATUS_ACTIVE && ! $user->is_guest;
    }

    public function view(User $user, OfflineRechargeRequest $request): bool
    {
        return (bool) $user->is_admin
            || $user->can(AdminPermission::APPROVE_OFFLINE_RECHARGES)
            || $user->can(AdminPermission::MANAGE_WALLETS)
            || $request->user_id === $user->getKey();
    }

    public function viewAny(User $user): bool
    {
        return $user->status === User::STATUS_ACTIVE && ! $user->is_guest;
    }

    public function reviewAny(User $user): bool
    {
        return (bool) $user->is_admin || $user->can(AdminPermission::APPROVE_OFFLINE_RECHARGES);
    }

    public function review(User $user, OfflineRechargeRequest $request): bool
    {
        return (bool) $user->is_admin || $user->can(AdminPermission::APPROVE_OFFLINE_RECHARGES);
    }
}
