<?php

namespace App\Features\VoiceRoom\Policies;

use App\Features\Users\Models\User;
use App\Features\VoiceRoom\Models\VoiceRoom;

final class VoiceRoomPolicy
{
    public function view(User $user, VoiceRoom $room): bool
    {
        return true;
    }

    public function join(User $user, VoiceRoom $room): bool
    {
        return true;
    }

    public function leave(User $user, VoiceRoom $room): bool
    {
        return true;
    }

    public function requestSeat(User $user, VoiceRoom $room): bool
    {
        return true;
    }

    public function approveSeat(User $user, VoiceRoom $room): bool
    {
        return $room->host_id === $user->getKey();
    }

    public function rejectSeat(User $user, VoiceRoom $room): bool
    {
        return $room->host_id === $user->getKey();
    }

    public function removeSpeaker(User $user, VoiceRoom $room): bool
    {
        return $room->host_id === $user->getKey();
    }

    public function muteSpeaker(User $user, VoiceRoom $room): bool
    {
        return $room->host_id === $user->getKey();
    }

    public function end(User $user, VoiceRoom $room): bool
    {
        return $room->host_id === $user->getKey();
    }
}
