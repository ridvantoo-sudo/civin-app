<?php

namespace App\Features\LiveStreaming\Policies;

use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;

final class LiveRoomPolicy
{
    public function view(User $user, LiveRoom $room): bool
    {
        return true;
    }

    public function start(User $user, LiveRoom $room): bool
    {
        return $room->host_id === $user->getKey();
    }

    public function end(User $user, LiveRoom $room): bool
    {
        return $room->host_id === $user->getKey();
    }

    public function join(User $user, LiveRoom $room): bool
    {
        return true;
    }

    public function leave(User $user, LiveRoom $room): bool
    {
        return true;
    }

    public function viewMessages(User $user, LiveRoom $room): bool
    {
        return true;
    }

    public function sendMessage(User $user, LiveRoom $room): bool
    {
        return true;
    }

    public function sendGift(User $user, LiveRoom $room): bool
    {
        return true;
    }
}
