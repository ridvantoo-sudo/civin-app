<?php

namespace App\Features\LiveShopping\Policies;

use App\Features\LiveShopping\Models\ShopOrder;
use App\Features\Users\Models\User;

final class ShopOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status === 'active' && ! $user->is_guest;
    }

    public function view(User $user, ShopOrder $order): bool
    {
        return $order->buyer_id === $user->getKey() || $order->seller_id === $user->getKey();
    }

    public function checkout(User $user): bool
    {
        return $user->status === 'active' && ! $user->is_guest;
    }

    public function updateShipping(User $user, ShopOrder $order): bool
    {
        return $user->status === 'active' && ! $user->is_guest && $order->seller_id === $user->getKey();
    }

    public function pay(User $user, ShopOrder $order): bool
    {
        return $user->status === 'active' && ! $user->is_guest && $order->buyer_id === $user->getKey();
    }
}
