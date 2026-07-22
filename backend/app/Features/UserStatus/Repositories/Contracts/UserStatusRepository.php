<?php

namespace App\Features\UserStatus\Repositories\Contracts;

use App\Features\Users\Models\User;
use App\Features\UserStatus\Models\UserStatus;

interface UserStatusRepository
{
    public function forUser(User $user): UserStatus;

    public function update(User $user, array $attributes): UserStatus;
}
