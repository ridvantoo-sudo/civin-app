<?php

namespace App\Features\Wallet\Policies;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\WithdrawRequest;

final class WithdrawRequestPolicy
{
    public function create(User $user): bool
    {
        return $user->status === User::STATUS_ACTIVE && ! $user->is_guest;
    }

    public function view(User $user, WithdrawRequest $request): bool
    {
        return (bool) $user->is_admin
            || $user->can(AdminPermission::APPROVE_WITHDRAWALS)
            || $user->can(AdminPermission::MANAGE_WALLETS)
            || $request->user_id === $user->getKey();
    }

    public function reviewAny(User $user): bool
    {
        return (bool) $user->is_admin || $user->can(AdminPermission::APPROVE_WITHDRAWALS);
    }

    public function review(User $user, WithdrawRequest $request): bool
    {
        return (bool) $user->is_admin || $user->can(AdminPermission::APPROVE_WITHDRAWALS);
    }
}
