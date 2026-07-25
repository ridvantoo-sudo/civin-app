<?php

namespace App\Features\Levels\Actions;

use App\Features\Levels\Models\UserLevelProgress;
use App\Features\Levels\Services\LevelService;
use App\Features\Users\Models\User;

final readonly class GetMyLevelProgress
{
    public function __construct(private LevelService $levels) {}

    public function execute(User $user): UserLevelProgress
    {
        return $this->levels->progressFor($user);
    }
}
