<?php

namespace App\Features\Admin\Actions;

use App\Features\Admin\Services\AdminModerationService;
use App\Features\Users\Models\User;
use App\Features\VoiceRoom\Models\VoiceRoom;

final readonly class TerminateVoiceRoom
{
    public function __construct(private AdminModerationService $moderation) {}

    public function execute(User $admin, VoiceRoom $room): VoiceRoom
    {
        return $this->moderation->terminateVoiceRoom($admin, $room);
    }
}
