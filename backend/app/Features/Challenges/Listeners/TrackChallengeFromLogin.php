<?php

namespace App\Features\Challenges\Listeners;

use App\Features\Authentication\Events\UserLoggedIn;
use App\Features\Challenges\Services\ChallengeService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class TrackChallengeFromLogin implements ShouldQueueAfterCommit
{
    public function __construct(private readonly ChallengeService $challenges) {}

    public function handle(UserLoggedIn $event): void
    {
        if ($event->user->is_guest) {
            return;
        }

        $this->challenges->trackLogin($event->user);
    }
}
