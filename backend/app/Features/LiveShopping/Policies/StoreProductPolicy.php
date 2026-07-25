<?php

namespace App\Features\LiveShopping\Policies;

use App\Features\LiveShopping\Models\StoreProduct;
use App\Features\Users\Models\User;

final class StoreProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status === 'active';
    }

    public function create(User $user): bool
    {
        return $user->status === 'active' && ! $user->is_guest;
    }

    public function update(User $user, StoreProduct $product): bool
    {
        return $user->status === 'active' && ! $user->is_guest && $product->seller_id === $user->getKey();
    }

    public function delete(User $user, StoreProduct $product): bool
    {
        return $this->update($user, $product);
    }
}
