<?php

namespace App\Features\Marketplace\Policies;

use App\Features\Marketplace\Models\UserMarketItem;
use App\Features\Users\Models\User;

final class UserMarketItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status === 'active';
    }

    public function view(User $user, UserMarketItem $item): bool
    {
        return $user->getKey() === $item->user_id;
    }

    public function purchase(User $user): bool
    {
        return $user->status === 'active' && ! $user->is_guest;
    }

    public function equip(User $user): bool
    {
        return $user->status === 'active' && ! $user->is_guest;
    }
}
