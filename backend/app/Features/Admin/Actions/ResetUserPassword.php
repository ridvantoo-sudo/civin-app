<?php

namespace App\Features\Admin\Actions;

use App\Features\Admin\Services\AdminModerationService;
use App\Features\Users\Models\User;

final readonly class ResetUserPassword
{
    public function __construct(private AdminModerationService $moderation) {}

    public function execute(User $admin, User $user, string $password): User
    {
        return $this->moderation->resetPassword($admin, $user, $password);
    }
}
