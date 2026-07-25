<?php

namespace App\Features\CreatorTools\Services;

use App\Features\CreatorTools\DTOs\CreateScheduleData;
use App\Features\CreatorTools\Models\StreamSchedule;
use App\Features\CreatorTools\Support\ScheduleStatus;
use App\Features\LiveStreaming\DTOs\CreateLiveRoomData;
use App\Features\LiveStreaming\Models\LiveCategory;
use App\Features\LiveStreaming\Services\LiveStreamingService;
use App\Features\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final readonly class StreamSchedulerService
{
    private const CREATE_RATE_KEY = 'creator-schedule:%s';

    public function __construct(private LiveStreamingService $live) {}

    public function create(User $host, CreateScheduleData $data): StreamSchedule
    {
        $this->ensureEligible($host);
        $this->enforceRateLimit(sprintf(self::CREATE_RATE_KEY, $host->getKey()));

        if ($data->scheduledAt <= now()) {
            throw ValidationException::withMessages(['scheduled_at' => 'Schedule time must be in the future.']);
        }

        if ($data->categoryId !== null && ! LiveCategory::query()->whereKey($data->categoryId)->exists()) {
            throw ValidationException::withMessages(['category_id' => 'Invalid category.']);
        }

        $max = (int) config('creator_tools.scheduler.max_upcoming_per_host', 20);
        $upcoming = StreamSchedule::query()
            ->where('host_id', $host->getKey())
            ->whereIn('status', [ScheduleStatus::SCHEDULED, ScheduleStatus::REMINDED])
            ->count();

        if ($upcoming >= $max) {
            throw ValidationException::withMessages(['schedule' => 'Too many upcoming schedules.']);
        }

        return StreamSchedule::query()->create([
            'host_id' => $host->getKey(),
            'category_id' => $data->categoryId,
            'title' => $data->title,
            'description' => $data->description,
            'thumbnail' => $data->thumbnail,
            'status' => ScheduleStatus::SCHEDULED,
            'scheduled_at' => $data->scheduledAt,
            'timezone' => $data->timezone !== '' ? $data->timezone : 'UTC',
            'metadata' => $data->metadata,
        ]);
    }

    public function cancel(User $host, StreamSchedule $schedule): StreamSchedule
    {
        $this->ensureOwner($host, $schedule);

        if (! in_array($schedule->status, [ScheduleStatus::SCHEDULED, ScheduleStatus::REMINDED], true)) {
            throw ValidationException::withMessages(['schedule' => 'Only upcoming schedules can be cancelled.']);
        }

        $schedule->forceFill([
            'status' => ScheduleStatus::CANCELLED,
            'cancelled_at' => now(),
        ])->save();

        return $schedule;
    }

    public function mine(User $host, int $perPage = 20): LengthAwarePaginator
    {
        $this->ensureEligible($host);

        return StreamSchedule::query()
            ->where('host_id', $host->getKey())
            ->latest('scheduled_at')
            ->paginate(max(1, min($perPage, 100)));
    }

    /**
     * Prepare due schedules: optionally create a LiveRoom shell and mark started.
     */
    public function processDue(): int
    {
        $due = StreamSchedule::query()
            ->whereIn('status', [ScheduleStatus::SCHEDULED, ScheduleStatus::REMINDED])
            ->where('scheduled_at', '<=', now())
            ->limit(50)
            ->get();

        $processed = 0;
        $autoRoom = (bool) config('creator_tools.scheduler.auto_create_room', true);

        foreach ($due as $schedule) {
            $roomId = $schedule->room_id;

            if ($autoRoom && $roomId === null) {
                $host = User::query()->find($schedule->host_id);
                $categoryId = $schedule->category_id
                    ?? LiveCategory::query()->where('status', 'active')->value('id');

                if ($host !== null && $categoryId !== null) {
                    $room = $this->live->create($host, new CreateLiveRoomData(
                        title: $schedule->title,
                        categoryId: (int) $categoryId,
                        description: $schedule->description,
                        thumbnail: $schedule->thumbnail,
                    ));
                    $roomId = $room->getKey();
                }
            }

            $schedule->forceFill([
                'status' => ScheduleStatus::STARTED,
                'started_at' => now(),
                'room_id' => $roomId,
            ])->save();
            $processed++;
        }

        return $processed;
    }

    public function markReminders(): int
    {
        $minutes = max(1, (int) config('creator_tools.scheduler.reminder_minutes', 15));
        $windowStart = now();
        $windowEnd = now()->addMinutes($minutes);

        $due = StreamSchedule::query()
            ->where('status', ScheduleStatus::SCHEDULED)
            ->whereNull('reminded_at')
            ->whereBetween('scheduled_at', [$windowStart, $windowEnd])
            ->limit(100)
            ->get();

        foreach ($due as $schedule) {
            $schedule->forceFill([
                'status' => ScheduleStatus::REMINDED,
                'reminded_at' => now(),
            ])->save();
        }

        return $due->count();
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== 'active') {
            throw new AuthorizationException('Only active hosts can manage stream schedules.');
        }
    }

    private function ensureOwner(User $user, StreamSchedule $schedule): void
    {
        $this->ensureEligible($user);
        if ($schedule->host_id !== $user->getKey()) {
            throw new AuthorizationException('You do not own this schedule.');
        }
    }

    private function enforceRateLimit(string $key): void
    {
        if (RateLimiter::tooManyAttempts($key, 20)) {
            throw ValidationException::withMessages(['schedule' => 'Too many schedule actions.']);
        }
        RateLimiter::hit($key, 60);
    }
}
