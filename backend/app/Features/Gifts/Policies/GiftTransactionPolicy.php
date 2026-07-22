<?php

namespace App\Features\Gifts\Policies;

use App\Features\Gifts\Models\GiftTransaction;
use App\Features\Users\Models\User;

final class GiftTransactionPolicy
{
    public function view(User $actor, GiftTransaction $transaction): bool
    {
        return $actor->getKey() === $transaction->sender_id
            || $actor->getKey() === $transaction->receiver_id;
    }
}
