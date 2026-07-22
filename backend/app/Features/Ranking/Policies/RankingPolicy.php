<?php

namespace App\Features\Ranking\Policies;

use App\Features\Users\Models\User;

final class RankingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }
}
