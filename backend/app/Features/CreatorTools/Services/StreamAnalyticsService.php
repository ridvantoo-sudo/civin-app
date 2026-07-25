<?php

namespace App\Features\CreatorTools\Services;

use App\Features\CreatorTools\Models\StreamAnalytics;
use App\Features\CreatorTools\Models\StreamAnalyticsSnapshot;
use App\Features\Gifts\Models\GiftTransaction;
use App\Features\LiveChat\Models\LiveMessage;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Models\LiveSession;
use App\Features\LiveStreaming\Models\LiveViewer;
use App\Features\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class StreamAnalyticsService
{
    public function finalizeForRoom(LiveRoom $room): StreamAnalytics
    {
        $session = LiveSession::query()->where('room_id', $room->getKey())->first();
        $duration = (int) ($session?->duration ?? 0);
        if ($duration === 0 && $room->started_at !== null && $room->ended_at !== null) {
            $duration = max(0, $room->ended_at->diffInSeconds($room->started_at));
        }

        $uniqueViewers = LiveViewer::query()->where('room_id', $room->getKey())->count();
        $chatMessages = LiveMessage::query()->where('room_id', $room->getKey())->count();
        $gifts = GiftTransaction::query()->where('room_id', $room->getKey());
        $giftsCount = (clone $gifts)->count();
        $giftsCoins = (int) (clone $gifts)->sum('coins');

        $avgWatch = 0.0;
        $watchRows = LiveViewer::query()
            ->where('room_id', $room->getKey())
            ->whereNotNull('joined_at')
            ->get(['joined_at', 'left_at']);

        if ($watchRows->isNotEmpty()) {
            $total = 0;
            foreach ($watchRows as $row) {
                $left = $row->left_at ?? $room->ended_at ?? now();
                $total += max(0, $left->diffInSeconds($row->joined_at));
            }
            $avgWatch = round($total / $watchRows->count(), 2);
        }

        $timeline = StreamAnalyticsSnapshot::query()
            ->where('room_id', $room->getKey())
            ->orderBy('captured_at')
            ->get(['viewer_count', 'chat_messages', 'gifts_count', 'captured_at'])
            ->map(fn (StreamAnalyticsSnapshot $s) => [
                'at' => $s->captured_at?->toISOString(),
                'viewers' => $s->viewer_count,
                'chat' => $s->chat_messages,
                'gifts' => $s->gifts_count,
            ])
            ->all();

        return StreamAnalytics::query()->updateOrCreate(
            ['room_id' => $room->getKey()],
            [
                'host_id' => $room->host_id,
                'duration_seconds' => $duration,
                'peak_viewers' => (int) ($session?->peak_viewers ?? $room->viewer_count),
                'unique_viewers' => $uniqueViewers,
                'chat_messages' => $chatMessages,
                'gifts_count' => $giftsCount,
                'gifts_coins' => $giftsCoins,
                'avg_watch_seconds' => $avgWatch,
                'timeline' => $timeline,
                'finalized_at' => now(),
            ],
        );
    }

    public function snapshotLiveRooms(): int
    {
        $rooms = LiveRoom::query()->where('status', 'live')->get();
        $count = 0;

        foreach ($rooms as $room) {
            StreamAnalyticsSnapshot::query()->create([
                'room_id' => $room->getKey(),
                'viewer_count' => (int) $room->viewer_count,
                'chat_messages' => LiveMessage::query()->where('room_id', $room->getKey())->count(),
                'gifts_count' => GiftTransaction::query()->where('room_id', $room->getKey())->count(),
                'captured_at' => now(),
            ]);
            $count++;
        }

        return $count;
    }

    public function forRoom(User $actor, LiveRoom $room): StreamAnalytics
    {
        $this->ensureHost($actor, $room);

        $analytics = StreamAnalytics::query()->where('room_id', $room->getKey())->first();
        if ($analytics !== null) {
            return $analytics;
        }

        return $this->finalizeForRoom($room);
    }

    public function overview(User $host, int $perPage = 20): LengthAwarePaginator
    {
        $this->ensureEligible($host);

        return StreamAnalytics::query()
            ->where('host_id', $host->getKey())
            ->latest('finalized_at')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function summary(User $host): array
    {
        $this->ensureEligible($host);

        $row = StreamAnalytics::query()
            ->where('host_id', $host->getKey())
            ->selectRaw('COUNT(*) as streams')
            ->selectRaw('COALESCE(SUM(duration_seconds),0) as total_duration')
            ->selectRaw('COALESCE(SUM(unique_viewers),0) as total_unique_viewers')
            ->selectRaw('COALESCE(SUM(gifts_coins),0) as total_gift_coins')
            ->selectRaw('COALESCE(AVG(peak_viewers),0) as avg_peak_viewers')
            ->first();

        return [
            'streams' => (int) ($row->streams ?? 0),
            'total_duration_seconds' => (int) ($row->total_duration ?? 0),
            'total_unique_viewers' => (int) ($row->total_unique_viewers ?? 0),
            'total_gift_coins' => (int) ($row->total_gift_coins ?? 0),
            'avg_peak_viewers' => (float) ($row->avg_peak_viewers ?? 0),
        ];
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== 'active') {
            throw new AuthorizationException('Only active hosts can view stream analytics.');
        }
    }

    private function ensureHost(User $user, LiveRoom $room): void
    {
        $this->ensureEligible($user);
        if ($room->host_id !== $user->getKey()) {
            throw new AuthorizationException('Only the host can view this room analytics.');
        }
    }
}
