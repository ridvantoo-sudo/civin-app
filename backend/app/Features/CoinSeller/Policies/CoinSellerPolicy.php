<?php

namespace App\Features\CoinSeller\Policies;

use App\Features\Admin\Support\AdminPermission;
use App\Features\CoinSeller\Models\CoinSeller;
use App\Features\Users\Models\User;

final class CoinSellerPolicy
{
    public function apply(User $user): bool
    {
        return $user->status === 'active' && ! $user->is_guest;
    }

    public function viewOwn(User $user): bool
    {
        return $user->status === 'active' && ! $user->is_guest;
    }

    public function manageOwn(User $user, CoinSeller $seller): bool
    {
        return $seller->isOwnedBy($user)
            && $user->status === 'active'
            && ! $user->is_guest;
    }

    public function sell(User $user, CoinSeller $seller): bool
    {
        return $this->manageOwn($user, $seller) && $seller->isActive();
    }

    public function redeem(User $user): bool
    {
        return $user->status === 'active' && ! $user->is_guest;
    }

    public function review(User $user): bool
    {
        return $user->is_admin || $user->can(AdminPermission::MANAGE_COIN_SELLERS);
    }

    public function allocate(User $user): bool
    {
        return $this->review($user);
    }

    public function viewAnyAdmin(User $user): bool
    {
        return $this->review($user);
    }
}
