<?php

namespace App\Features\LiveChat\Repositories\Eloquent;

use App\Features\LiveChat\DTOs\SendLiveMessageData;
use App\Features\LiveChat\Models\LiveChatModerator;
use App\Features\LiveChat\Models\LiveChatSetting;
use App\Features\LiveChat\Models\LiveMessage;
use App\Features\LiveChat\Repositories\Contracts\LiveMessageRepository;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Models\LiveViewer;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EloquentLiveMessageRepository implements LiveMessageRepository
{
    public function initializeRoom(LiveRoom $room): LiveChatSetting
    {
        return LiveChatSetting::query()->firstOrCreate(
            ['room_id' => $room->getKey()],
            [
                'slow_mode_seconds' => 0,
                'followers_only' => false,
                'allow_links' => true,
            ],
        );
    }

    public function settingsFor(LiveRoom $room): LiveChatSetting
    {
        return $this->initializeRoom($room);
    }

    public function send(LiveRoom $room, ?User $user, SendLiveMessageData $data): LiveMessage
    {
        return DB::transaction(function () use ($room, $user, $data): LiveMessage {
            $lockedRoom = LiveRoom::query()->lockForUpdate()->findOrFail($room->getKey());

            if ($data->type === LiveMessage::TYPE_TEXT) {
                if ($lockedRoom->status !== 'live') {
                    throw ValidationException::withMessages(['room' => 'Chat is only available while the room is live.']);
                }

                if ($user === null || ! $this->isActiveParticipant($lockedRoom, $user)) {
                    throw ValidationException::withMessages(['room' => 'Only the host or an active viewer can send chat messages.']);
                }
            }

            return LiveMessage::query()->create([
                'room_id' => $lockedRoom->getKey(),
                'user_id' => $user?->getKey(),
                'message' => $data->message,
                'type' => $data->type,
                'metadata' => $data->metadata,
            ])->load('user.profile', 'user.socialStatus');
        });
    }

    public function forRoom(LiveRoom $room, int $perPage): LengthAwarePaginator
    {
        return LiveMessage::query()
            ->where('room_id', $room->getKey())
            ->with('user.profile', 'user.socialStatus')
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function delete(LiveMessage $message): LiveMessage
    {
        return DB::transaction(function () use ($message): LiveMessage {
            $locked = LiveMessage::query()->lockForUpdate()->findOrFail($message->getKey());
            $locked->delete();

            return $locked;
        });
    }

    public function isActiveParticipant(LiveRoom $room, User $user): bool
    {
        if ($room->host_id === $user->getKey()) {
            return true;
        }

        return LiveViewer::query()
            ->where('room_id', $room->getKey())
            ->where('user_id', $user->getKey())
            ->whereNull('left_at')
            ->exists();
    }

    public function isModerator(LiveRoom $room, User $user): bool
    {
        return LiveChatModerator::query()
            ->where('room_id', $room->getKey())
            ->where('user_id', $user->getKey())
            ->exists();
    }

    public function latestUserTextAt(LiveRoom $room, User $user): ?Carbon
    {
        $createdAt = LiveMessage::query()
            ->where('room_id', $room->getKey())
            ->where('user_id', $user->getKey())
            ->where('type', LiveMessage::TYPE_TEXT)
            ->latest('created_at')
            ->value('created_at');

        return $createdAt !== null ? Carbon::parse($createdAt) : null;
    }

    public function recentDuplicateExists(LiveRoom $room, User $user, string $message, int $withinSeconds = 10): bool
    {
        return LiveMessage::query()
            ->where('room_id', $room->getKey())
            ->where('user_id', $user->getKey())
            ->where('type', LiveMessage::TYPE_TEXT)
            ->where('message', $message)
            ->where('created_at', '>=', now()->subSeconds($withinSeconds))
            ->exists();
    }
}
