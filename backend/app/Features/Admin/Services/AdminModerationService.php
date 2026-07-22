<?php

namespace App\Features\Admin\Services;

use App\Features\LiveChat\Events\MessageDeleted;
use App\Features\LiveChat\Models\LiveMessage;
use App\Features\LiveChat\Repositories\Contracts\LiveMessageRepository;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Services\LiveStreamingService;
use App\Features\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AdminModerationService
{
    public function __construct(
        private LiveStreamingService $liveStreaming,
        private LiveMessageRepository $messages,
        private AdminAuditLogger $audit,
    ) {}

    public function banUser(User $admin, User $user, ?string $reason = null): User
    {
        if ($admin->is($user)) {
            throw ValidationException::withMessages(['user' => 'You cannot ban yourself.']);
        }

        if ($user->hasRole(\App\Features\Admin\Support\AdminRole::SUPER_ADMIN) && ! $admin->hasRole(\App\Features\Admin\Support\AdminRole::SUPER_ADMIN)) {
            throw ValidationException::withMessages(['user' => 'Only a Super Admin can ban another Super Admin.']);
        }

        return DB::transaction(function () use ($admin, $user, $reason): User {
            $user->forceFill([
                'status' => User::STATUS_BANNED,
                'banned_at' => now(),
                'ban_reason' => $reason,
            ])->save();

            $user->tokens()->delete();

            $liveRooms = LiveRoom::query()
                ->where('host_id', $user->getKey())
                ->where('status', 'live')
                ->get();

            foreach ($liveRooms as $room) {
                $this->liveStreaming->end($room);
            }

            $this->audit->log($admin, 'user.banned', $user, [
                'reason' => $reason,
                'terminated_rooms' => $liveRooms->pluck('id')->all(),
            ]);

            return $user->fresh();
        });
    }

    public function unbanUser(User $admin, User $user): User
    {
        $user->forceFill([
            'status' => User::STATUS_ACTIVE,
            'banned_at' => null,
            'ban_reason' => null,
        ])->save();

        $this->audit->log($admin, 'user.unbanned', $user);

        return $user->fresh();
    }

    public function terminateLiveRoom(User $admin, LiveRoom $room): LiveRoom
    {
        if ($room->status === 'ended') {
            throw ValidationException::withMessages(['room' => 'This live room has already ended.']);
        }

        $ended = $this->liveStreaming->end($room);

        $this->audit->log($admin, 'live_room.terminated', $ended, [
            'host_id' => $ended->host_id,
            'previous_status' => $room->status,
        ]);

        return $ended;
    }

    public function deleteLiveMessage(User $admin, LiveMessage $message): LiveMessage
    {
        $room = $message->room()->firstOrFail();
        $deleted = $this->messages->delete($message);

        MessageDeleted::dispatch($room->getKey(), $message->getKey());

        $this->audit->log($admin, 'live_message.deleted', $deleted, [
            'room_id' => $room->getKey(),
            'message_user_id' => $message->user_id,
            'type' => $message->type,
        ]);

        return $deleted;
    }
}
