<?php

namespace App\Features\Levels\Listeners;

use App\Features\Authentication\Events\UserLoggedIn;
use App\Features\Levels\Services\LevelService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class AwardXpFromLogin implements ShouldQueueAfterCommit
{
    public function __construct(private readonly LevelService $levels) {}

    public function handle(UserLoggedIn $event): void
    {
        if ($event->user->is_guest) {
            return;
        }

        $this->levels->awardLogin($event->user);
    }
}
