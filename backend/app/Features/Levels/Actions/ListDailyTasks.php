<?php

namespace App\Features\Levels\Actions;

use App\Features\Levels\Services\LevelService;
use App\Features\Users\Models\User;
use Illuminate\Support\Collection;

final readonly class ListDailyTasks
{
    public function __construct(private LevelService $levels) {}

    public function execute(User $user): Collection
    {
        return $this->levels->listDailyTasks($user);
    }
}
