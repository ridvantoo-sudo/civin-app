<?php

namespace App\Features\UserStatus\Services;

use App\Features\Users\Models\User;
use App\Features\UserStatus\DTOs\UpdateUserStatusData;
use App\Features\UserStatus\Events\UserStatusChanged;
use App\Features\UserStatus\Models\UserStatus;
use App\Features\UserStatus\Repositories\Contracts\UserStatusRepository;

final readonly class UserStatusService
{
    public function __construct(private UserStatusRepository $statuses) {}

    public function show(User $user): UserStatus
    {
        return $this->statuses->forUser($user);
    }

    public function update(User $user, UpdateUserStatusData $data): UserStatus
    {
        $current = $this->statuses->forUser($user);
        $attributes = [];

        if ($data->isOnline !== null) {
            $attributes['is_online'] = $data->isOnline;
            $attributes['last_seen_at'] = $data->isOnline ? $current->last_seen_at : now();
        }

        if ($data->isLive !== null) {
            $attributes['is_live'] = $data->isLive;
            $attributes['live_started_at'] = $data->isLive
                ? ($current->live_started_at ?? now())
                : null;
        }

        $status = $this->statuses->update($user, $attributes);
        UserStatusChanged::dispatch($status);

        return $status;
    }
}
