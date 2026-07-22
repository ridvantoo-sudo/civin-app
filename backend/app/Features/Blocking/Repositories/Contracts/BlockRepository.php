<?php

namespace App\Features\Blocking\Repositories\Contracts;

use App\Features\Blocking\Models\Block;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BlockRepository
{
    public function block(User $blocker, User $blocked): Block;

    public function unblock(User $blocker, User $blocked): void;

    public function existsBetween(User|string $first, User|string $second): bool;

    public function blockedBy(User $user, int $perPage): LengthAwarePaginator;
}
