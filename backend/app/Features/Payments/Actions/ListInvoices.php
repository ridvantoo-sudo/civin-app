<?php

namespace App\Features\Payments\Actions;

use App\Features\Payments\Models\Invoice;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListInvoices
{
    public function forUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return Invoice::query()
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->getKey())
                    ->orWhere('seller_id', $user->getKey());
            })
            ->with('lines')
            ->latest()
            ->paginate(max(1, min($perPage, 100)));
    }
}
