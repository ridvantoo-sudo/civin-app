<?php

namespace App\Features\Payments\Policies;

use App\Features\Payments\Models\Invoice;
use App\Features\Users\Models\User;

final class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status === 'active' && ! $user->is_guest;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $invoice->user_id === $user->getKey() || $invoice->seller_id === $user->getKey();
    }
}
