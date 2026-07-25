<?php

namespace App\Features\Challenges\Listeners;

use App\Features\Challenges\Services\ChallengeService;
use App\Features\LiveStreaming\Events\LiveEnded;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class TrackChallengeFromLive implements ShouldQueueAfterCommit
{
    public function __construct(private readonly ChallengeService $challenges) {}

    public function handle(LiveEnded $event): void
    {
        $host = User::query()->find($event->hostId);
        if ($host === null) {
            return;
        }

        $minutes = intdiv(max(0, $event->durationSeconds), 60);
        if ($minutes < 1) {
            return;
        }

        $this->challenges->trackLive($host, $minutes);
    }
}
