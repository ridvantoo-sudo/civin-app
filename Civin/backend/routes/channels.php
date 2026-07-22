<?php

use App\Features\LiveChat\Models\LiveChatModerator;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use App\Features\VoiceRoom\Models\VoiceRoom;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{userId}', fn (User $user, string $userId): bool => $user->id === $userId);

Broadcast::channel('user.wallet.{userId}', fn (User $user, string $userId): bool => $user->id === $userId);

Broadcast::channel('live.room.{roomId}', function (User $user, string $roomId): bool {
    $room = LiveRoom::query()->find($roomId);

    if ($room === null) {
        return false;
    }

    if ($room->host_id === $user->getKey()) {
        return true;
    }

    if ($room->viewers()->where('user_id', $user->getKey())->whereNull('left_at')->exists()) {
        return true;
    }

    return LiveChatModerator::query()
        ->where('room_id', $roomId)
        ->where('user_id', $user->getKey())
        ->exists();
});

Broadcast::channel('voice.room.{roomId}', function (User $user, string $roomId): bool {
    $room = VoiceRoom::query()->find($roomId);

    if ($room === null) {
        return false;
    }

    if ($room->host_id === $user->getKey()) {
        return true;
    }

    return $room->participants()
        ->where('user_id', $user->getKey())
        ->whereNull('left_at')
        ->exists();
});
