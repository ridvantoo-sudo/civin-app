<?php

namespace App\Features\CreatorTools\Services;

use App\Features\CreatorTools\Models\StreamReplay;
use App\Features\CreatorTools\Support\MediaStatus;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Models\LiveSession;
use App\Features\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class StreamReplayService
{
    public function createFromEndedRoom(LiveRoom $room): ?StreamReplay
    {
        if (! (bool) config('creator_tools.replays.auto_create_on_live_end', true)) {
            return null;
        }

        $existing = StreamReplay::query()->where('room_id', $room->getKey())->first();
        if ($existing !== null) {
            return $existing;
        }

        $session = LiveSession::query()->where('room_id', $room->getKey())->first();
        $duration = (int) ($session?->duration ?? 0);
        $min = (int) config('creator_tools.replays.min_duration_seconds', 30);

        if ($duration < $min) {
            return null;
        }

        $retention = (int) config('creator_tools.replays.retention_days', 30);
        $cdn = rtrim((string) config('creator_tools.replays.cdn_base_url'), '/');

        return StreamReplay::query()->create([
            'room_id' => $room->getKey(),
            'host_id' => $room->host_id,
            'title' => $room->title,
            'status' => MediaStatus::READY,
            'duration_seconds' => $duration,
            'peak_viewers' => (int) ($session?->peak_viewers ?? $room->viewer_count),
            'media_url' => "{$cdn}/replays/{$room->getKey()}.mp4",
            'thumbnail_url' => $room->thumbnail,
            'expires_at' => $retention > 0 ? now()->addDays($retention) : null,
            'metadata' => ['source' => 'live_end'],
        ]);
    }

    public function mine(User $host, int $perPage = 20): LengthAwarePaginator
    {
        $this->ensureEligible($host);

        return StreamReplay::query()
            ->where('host_id', $host->getKey())
            ->where('status', '!=', MediaStatus::EXPIRED)
            ->latest()
            ->paginate(max(1, min($perPage, 100)));
    }

    public function show(User $viewer, StreamReplay $replay): StreamReplay
    {
        if ($replay->status === MediaStatus::EXPIRED) {
            throw new AuthorizationException('This replay has expired.');
        }

        $replay->increment('views_count');

        return $replay->fresh() ?? $replay;
    }

    public function forRoom(LiveRoom $room): ?StreamReplay
    {
        return StreamReplay::query()->where('room_id', $room->getKey())->first();
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== 'active') {
            throw new AuthorizationException('Only active users can browse replays.');
        }
    }
}
