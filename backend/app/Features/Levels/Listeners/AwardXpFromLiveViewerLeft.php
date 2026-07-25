<?php

namespace App\Features\Levels\Listeners;

use App\Features\Levels\Services\LevelService;
use App\Features\LiveStreaming\Events\LiveViewerLeft;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class AwardXpFromLiveViewerLeft implements ShouldQueueAfterCommit
{
    public function __construct(private readonly LevelService $levels) {}

    public function handle(LiveViewerLeft $event): void
    {
        $viewer = User::query()->find($event->viewerId);
        if ($viewer === null) {
            return;
        }

        $this->levels->awardWatching(
            $viewer,
            $event->roomId,
            $event->watchSeconds,
            $event->joinedAtKey,
        );
    }
}
