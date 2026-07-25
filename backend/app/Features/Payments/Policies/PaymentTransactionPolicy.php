<?php

namespace App\Features\Payments\Policies;

use App\Features\Payments\Models\PaymentTransaction;
use App\Features\Users\Models\User;

final class PaymentTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status === 'active' && ! $user->is_guest;
    }

    public function view(User $user, PaymentTransaction $transaction): bool
    {
        return $transaction->user_id === $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->status === 'active' && ! $user->is_guest;
    }

    public function refund(User $user, PaymentTransaction $transaction): bool
    {
        // Buyers may request refunds on their own transactions; broader admin
        // refunds are handled through the Filament admin panel.
        return $transaction->user_id === $user->getKey() && ! $user->is_guest;
    }
}
