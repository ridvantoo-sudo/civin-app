<?php

namespace App\Features\UserStatus\Actions;

use App\Features\Users\Models\User;
use App\Features\UserStatus\DTOs\UpdateUserStatusData;
use App\Features\UserStatus\Models\UserStatus;
use App\Features\UserStatus\Services\UserStatusService;

final readonly class UpdateUserStatus
{
    public function __construct(private UserStatusService $statuses) {}

    public function execute(User $user, UpdateUserStatusData $data): UserStatus
    {
        return $this->statuses->update($user, $data);
    }
}
