<?php

namespace App\Features\Admin\Actions;

use App\Features\Admin\Services\AdminModerationService;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;

final readonly class TerminateLiveRoom
{
    public function __construct(private AdminModerationService $moderation) {}

    public function execute(User $admin, LiveRoom $room): LiveRoom
    {
        return $this->moderation->terminateLiveRoom($admin, $room);
    }
}
