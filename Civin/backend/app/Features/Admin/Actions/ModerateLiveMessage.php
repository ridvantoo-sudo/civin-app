<?php

namespace App\Features\Admin\Actions;

use App\Features\Admin\Services\AdminModerationService;
use App\Features\LiveChat\Models\LiveMessage;
use App\Features\Users\Models\User;

final readonly class ModerateLiveMessage
{
    public function __construct(private AdminModerationService $moderation) {}

    public function execute(User $admin, LiveMessage $message): LiveMessage
    {
        return $this->moderation->deleteLiveMessage($admin, $message);
    }
}
