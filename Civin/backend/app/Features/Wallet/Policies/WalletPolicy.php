<?php

namespace App\Features\Wallet\Policies;

use App\Features\Users\Models\User;
use App\Features\Wallet\Models\Wallet;

final class WalletPolicy
{
    public function view(User $actor, Wallet $wallet): bool
    {
        return $actor->getKey() === $wallet->user_id;
    }
}
