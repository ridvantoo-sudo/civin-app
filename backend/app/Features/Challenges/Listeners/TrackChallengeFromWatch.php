<?php

namespace App\Features\Challenges\Listeners;

use App\Features\Challenges\Services\ChallengeService;
use App\Features\LiveStreaming\Events\LiveViewerLeft;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class TrackChallengeFromWatch implements ShouldQueueAfterCommit
{
    public function __construct(private readonly ChallengeService $challenges) {}

    public function handle(LiveViewerLeft $event): void
    {
        $viewer = User::query()->find($event->viewerId);
        if ($viewer === null) {
            return;
        }

        $minutes = intdiv(max(0, $event->watchSeconds), 60);
        if ($minutes < 1) {
            return;
        }

        $this->challenges->trackWatching($viewer, $minutes);
    }
}
