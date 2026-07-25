<?php

namespace App\Features\Admin\Actions;

use App\Features\Admin\Services\AdminModerationService;
use App\Features\Users\Models\User;

final readonly class ForceDeleteUser
{
    public function __construct(private AdminModerationService $moderation) {}

    public function execute(User $admin, User $user): void
    {
        $this->moderation->forceDeleteUser($admin, $user);
    }
}
