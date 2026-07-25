<?php

namespace App\Features\Admin\Actions;

use App\Features\Admin\Services\AdminModerationService;
use App\Features\PkBattle\Models\PkBattle;
use App\Features\Users\Models\User;

final readonly class EndPkBattle
{
    public function __construct(private AdminModerationService $moderation) {}

    public function execute(User $admin, PkBattle $battle): PkBattle
    {
        return $this->moderation->endPkBattle($admin, $battle);
    }
}
