<?php

namespace App\Features\Levels\Actions;

use App\Features\Levels\Models\XpTransaction;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListXpHistory
{
    public function execute(User $user, int $perPage = 30): LengthAwarePaginator
    {
        return XpTransaction::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
