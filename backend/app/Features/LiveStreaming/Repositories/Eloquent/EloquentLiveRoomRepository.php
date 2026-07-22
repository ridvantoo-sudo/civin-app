<?php

namespace App\Features\LiveStreaming\Repositories\Eloquent;

use App\Features\LiveStreaming\DTOs\CreateLiveRoomData;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Models\LiveSession;
use App\Features\LiveStreaming\Models\LiveViewer;
use App\Features\LiveStreaming\Repositories\Contracts\LiveRoomRepository;
use App\Features\Users\Models\User;
use App\Features\UserStatus\Models\UserStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EloquentLiveRoomRepository implements LiveRoomRepository
{
    public function create(
        User $host,
        CreateLiveRoomData $data,
        string $roomId,
        string $channel,
        int $streamUid,
    ): LiveRoom {
        return LiveRoom::query()->create([
            'id' => $roomId,
            'host_id' => $host->getKey(),
            'category_id' => $data->categoryId,
            'title' => $data->title,
            'description' => $data->description,
            'thumbnail' => $data->thumbnail,
            'agora_channel_name' => $channel,
            'stream_uid' => $streamUid,
            'status' => 'created',
        ])->load('host.profile', 'category');
    }

    public function start(LiveRoom $room): LiveRoom
    {
        return DB::transaction(function () use ($room): LiveRoom {
            $lockedRoom = LiveRoom::query()->lockForUpdate()->findOrFail($room->getKey());
            User::query()->lockForUpdate()->findOrFail($lockedRoom->host_id);

            if ($lockedRoom->status !== 'created') {
                throw ValidationException::withMessages(['room' => 'Only a created room can be started.']);
            }

            if (LiveRoom::query()
                ->where('host_id', $lockedRoom->host_id)
                ->where('status', 'live')
                ->where('id', '!=', $lockedRoom->getKey())
                ->exists()) {
                throw ValidationException::withMessages(['room' => 'The host already has an active live room.']);
            }

            $startedAt = now();
            $lockedRoom->update(['status' => 'live', 'started_at' => $startedAt, 'ended_at' => null]);

            $status = UserStatus::query()->withTrashed()->firstOrNew(['user_id' => $lockedRoom->host_id]);
            $status->deleted_at = null;
            $status->fill(['is_live' => true, 'live_started_at' => $startedAt])->save();

            LiveSession::query()->create([
                'room_id' => $lockedRoom->getKey(),
                'peak_viewers' => 0,
            ]);

            return $this->show($lockedRoom->fresh());
        });
    }

    public function join(LiveRoom $room, User $viewer): array
    {
        return DB::transaction(function () use ($room, $viewer): array {
            $lockedRoom = LiveRoom::query()->lockForUpdate()->findOrFail($room->getKey());

            if ($lockedRoom->status !== 'live') {
                throw ValidationException::withMessages(['room' => 'Viewers can only join a live room.']);
            }

            if ($lockedRoom->host_id === $viewer->getKey()) {
                throw ValidationException::withMessages(['room' => 'The host cannot join as a viewer.']);
            }

            $membership = LiveViewer::query()
                ->where('room_id', $lockedRoom->getKey())
                ->where('user_id', $viewer->getKey())
                ->lockForUpdate()
                ->first();

            if ($membership !== null && $membership->left_at === null) {
                return ['room' => $this->show($lockedRoom), 'changed' => false];
            }

            if ($membership === null) {
                LiveViewer::query()->create([
                    'room_id' => $lockedRoom->getKey(),
                    'user_id' => $viewer->getKey(),
                    'joined_at' => now(),
                ]);
            } else {
                $membership->update(['joined_at' => now(), 'left_at' => null]);
            }

            $viewerCount = $lockedRoom->viewer_count + 1;
            $lockedRoom->update(['viewer_count' => $viewerCount]);

            $session = LiveSession::query()->where('room_id', $lockedRoom->getKey())->lockForUpdate()->firstOrFail();
            if ($viewerCount > $session->peak_viewers) {
                $session->update(['peak_viewers' => $viewerCount]);
            }

            return ['room' => $this->show($lockedRoom->fresh()), 'changed' => true];
        });
    }

    public function leave(LiveRoom $room, User $viewer): array
    {
        return DB::transaction(function () use ($room, $viewer): array {
            $lockedRoom = LiveRoom::query()->lockForUpdate()->findOrFail($room->getKey());
            $membership = LiveViewer::query()
                ->where('room_id', $lockedRoom->getKey())
                ->where('user_id', $viewer->getKey())
                ->lockForUpdate()
                ->first();

            if ($membership === null || $membership->left_at !== null) {
                return ['room' => $this->show($lockedRoom), 'changed' => false];
            }

            $membership->update(['left_at' => now()]);
            $lockedRoom->update(['viewer_count' => max(0, $lockedRoom->viewer_count - 1)]);

            return ['room' => $this->show($lockedRoom->fresh()), 'changed' => true];
        });
    }

    public function end(LiveRoom $room): LiveRoom
    {
        return DB::transaction(function () use ($room): LiveRoom {
            $lockedRoom = LiveRoom::query()->lockForUpdate()->findOrFail($room->getKey());
            User::query()->lockForUpdate()->findOrFail($lockedRoom->host_id);

            if ($lockedRoom->status !== 'live') {
                throw ValidationException::withMessages(['room' => 'Only a live room can be ended.']);
            }

            $endedAt = now();
            $duration = max(0, (int) $lockedRoom->started_at->diffInSeconds($endedAt));
            $finalViewerCount = $lockedRoom->viewer_count;

            $session = LiveSession::query()->where('room_id', $lockedRoom->getKey())->lockForUpdate()->firstOrFail();
            $session->update([
                'duration' => $duration,
                'peak_viewers' => max($session->peak_viewers, $finalViewerCount),
            ]);

            LiveViewer::query()
                ->where('room_id', $lockedRoom->getKey())
                ->whereNull('left_at')
                ->update(['left_at' => $endedAt]);

            UserStatus::query()->withTrashed()->where('user_id', $lockedRoom->host_id)->update([
                'is_live' => false,
                'live_started_at' => null,
                'updated_at' => $endedAt,
            ]);

            $lockedRoom->update(['status' => 'ended', 'ended_at' => $endedAt, 'viewer_count' => 0]);

            return $this->show($lockedRoom->fresh());
        });
    }

    public function live(int $perPage): LengthAwarePaginator
    {
        return LiveRoom::query()
            ->where('status', 'live')
            ->with('host.profile', 'category')
            ->latest('started_at')
            ->paginate($perPage);
    }

    public function show(LiveRoom $room): LiveRoom
    {
        return $room->load('host.profile', 'category', 'session');
    }

    public function streamUidExists(int $uid): bool
    {
        return LiveRoom::query()->where('stream_uid', $uid)->exists();
    }
}
