<?php

namespace App\Features\Admin\Actions;

use App\Features\Admin\Services\AdminModerationService;
use App\Features\Users\Models\User;

final readonly class BanUser
{
    public function __construct(private AdminModerationService $moderation) {}

    public function execute(User $admin, User $user, ?string $reason = null): User
    {
        return $this->moderation->banUser($admin, $user, $reason);
    }
}
