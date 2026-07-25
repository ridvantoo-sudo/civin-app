<?php

namespace App\Features\Admin\Actions;

use App\Features\Admin\Services\AdminModerationService;
use App\Features\Users\Models\User;
use App\Features\VoicePkBattle\Models\VoicePkBattle;

final readonly class EndVoicePkBattle
{
    public function __construct(private AdminModerationService $moderation) {}

    public function execute(User $admin, VoicePkBattle $battle): VoicePkBattle
    {
        return $this->moderation->endVoicePkBattle($admin, $battle);
    }
}
