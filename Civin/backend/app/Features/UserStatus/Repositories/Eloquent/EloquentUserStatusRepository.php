<?php

namespace App\Features\UserStatus\Repositories\Eloquent;

use App\Features\Users\Models\User;
use App\Features\UserStatus\Models\UserStatus;
use App\Features\UserStatus\Repositories\Contracts\UserStatusRepository;

final class EloquentUserStatusRepository implements UserStatusRepository
{
    public function forUser(User $user): UserStatus
    {
        return UserStatus::query()->firstOrCreate(['user_id' => $user->getKey()]);
    }

    public function update(User $user, array $attributes): UserStatus
    {
        $status = UserStatus::query()->withTrashed()->firstOrNew(['user_id' => $user->getKey()]);
        $status->deleted_at = null;
        $status->fill($attributes)->save();

        return $status->fresh();
    }
}
