<?php

namespace App\Features\CreatorTools\Listeners;

use App\Features\CreatorTools\Services\StreamAnalyticsService;
use App\Features\CreatorTools\Services\StreamReplayService;
use App\Features\LiveStreaming\Events\LiveEnded;
use App\Features\LiveStreaming\Models\LiveRoom;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cross-feature seam: when a live ends, finalize host analytics and create a
 * Replay Center entry. Failures are logged and never roll back LiveStreaming.
 */
final class FinalizeCreatorToolsOnLiveEnd
{
    public function __construct(
        private readonly StreamAnalyticsService $analytics,
        private readonly StreamReplayService $replays,
    ) {}

    public function handle(LiveEnded $event): void
    {
        $room = LiveRoom::query()->find($event->roomId);
        if ($room === null) {
            return;
        }

        try {
            $this->analytics->finalizeForRoom($room);
        } catch (Throwable $exception) {
            Log::warning('creator_tools.analytics_finalize_failed', [
                'room_id' => $event->roomId,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            $this->replays->createFromEndedRoom($room);
        } catch (Throwable $exception) {
            Log::warning('creator_tools.replay_create_failed', [
                'room_id' => $event->roomId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
