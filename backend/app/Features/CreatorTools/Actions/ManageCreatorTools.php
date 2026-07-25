<?php

namespace App\Features\CreatorTools\Actions;

use App\Features\CreatorTools\DTOs\CreateClipData;
use App\Features\CreatorTools\DTOs\CreateScheduleData;
use App\Features\CreatorTools\Models\RtmpIngestCredential;
use App\Features\CreatorTools\Models\StreamAnalytics;
use App\Features\CreatorTools\Models\StreamClip;
use App\Features\CreatorTools\Models\StreamReplay;
use App\Features\CreatorTools\Models\StreamSchedule;
use App\Features\CreatorTools\Services\RtmpIngestService;
use App\Features\CreatorTools\Services\StreamAnalyticsService;
use App\Features\CreatorTools\Services\StreamClipService;
use App\Features\CreatorTools\Services\StreamReplayService;
use App\Features\CreatorTools\Services\StreamSchedulerService;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ManageCreatorTools
{
    public function __construct(
        private StreamSchedulerService $scheduler,
        private StreamClipService $clips,
        private StreamReplayService $replays,
        private RtmpIngestService $rtmp,
        private StreamAnalyticsService $analytics,
    ) {}

    public function createSchedule(User $user, CreateScheduleData $data): StreamSchedule
    {
        return $this->scheduler->create($user, $data);
    }

    public function cancelSchedule(User $user, StreamSchedule $schedule): StreamSchedule
    {
        return $this->scheduler->cancel($user, $schedule);
    }

    public function mySchedules(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->scheduler->mine($user, $perPage);
    }

    public function createClip(User $user, LiveRoom $room, CreateClipData $data): StreamClip
    {
        return $this->clips->create($user, $room, $data);
    }

    public function myClips(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->clips->mine($user, $perPage);
    }

    public function roomClips(LiveRoom $room, ?string $kind, int $perPage = 20): LengthAwarePaginator
    {
        return $this->clips->forRoom($room, $kind, $perPage);
    }

    public function myReplays(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->replays->mine($user, $perPage);
    }

    public function showReplay(User $user, StreamReplay $replay): StreamReplay
    {
        return $this->replays->show($user, $replay);
    }

    public function issueRtmp(User $user, ?LiveRoom $room = null): RtmpIngestCredential
    {
        return $this->rtmp->issue($user, $room);
    }

    public function activeRtmp(User $user, ?LiveRoom $room = null): ?RtmpIngestCredential
    {
        return $this->rtmp->activeForHost($user, $room);
    }

    public function revokeRtmp(User $user, RtmpIngestCredential $credential): RtmpIngestCredential
    {
        return $this->rtmp->revoke($user, $credential);
    }

    public function roomAnalytics(User $user, LiveRoom $room): StreamAnalytics
    {
        return $this->analytics->forRoom($user, $room);
    }

    public function analyticsOverview(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->analytics->overview($user, $perPage);
    }

    public function analyticsSummary(User $user): array
    {
        return $this->analytics->summary($user);
    }
}
