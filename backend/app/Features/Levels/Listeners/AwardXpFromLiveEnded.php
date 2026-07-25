<?php

namespace App\Features\Levels\Listeners;

use App\Features\Levels\Services\LevelService;
use App\Features\LiveStreaming\Events\LiveEnded;
use App\Features\LiveStreaming\Models\LiveViewer;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class AwardXpFromLiveEnded implements ShouldQueueAfterCommit
{
    public function __construct(private readonly LevelService $levels) {}

    public function handle(LiveEnded $event): void
    {
        $host = User::query()->find($event->hostId);
        if ($host !== null) {
            $this->levels->awardLive($host, $event->roomId, $event->durationSeconds);
        }

        $viewers = LiveViewer::query()
            ->where('room_id', $event->roomId)
            ->whereNotNull('joined_at')
            ->whereNotNull('left_at')
            ->with('user')
            ->get();

        foreach ($viewers as $viewer) {
            if (! $viewer->user instanceof User) {
                continue;
            }

            $watchSeconds = max(0, (int) $viewer->joined_at->diffInSeconds($viewer->left_at));
            $joinedAtKey = $viewer->joined_at->toISOString();

            $this->levels->awardWatching(
                $viewer->user,
                $event->roomId,
                $watchSeconds,
                $joinedAtKey,
            );
        }
    }
}
