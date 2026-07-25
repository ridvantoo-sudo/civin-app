<?php

namespace App\Features\Levels\Actions;

use App\Features\Levels\DTOs\XpAwardResult;
use App\Features\Levels\Services\LevelService;
use App\Features\Users\Models\User;

final readonly class ClaimDailyTask
{
    public function __construct(private LevelService $levels) {}

    public function execute(User $user, string $taskId): XpAwardResult
    {
        return $this->levels->claimDailyTask($user, $taskId);
    }
}
