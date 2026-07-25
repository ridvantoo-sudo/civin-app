<?php

namespace App\Features\Admin\Actions;

use App\Features\Admin\Services\AdminModerationService;
use App\Features\MultiPkBattle\Models\MultiPkBattle;
use App\Features\Users\Models\User;

final readonly class EndMultiPkBattle
{
    public function __construct(private AdminModerationService $moderation) {}

    public function execute(User $admin, MultiPkBattle $battle): MultiPkBattle
    {
        return $this->moderation->endMultiPkBattle($admin, $battle);
    }
}
