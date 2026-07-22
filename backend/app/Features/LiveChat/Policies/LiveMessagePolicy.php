<?php

namespace App\Features\LiveChat\Policies;

use App\Features\LiveChat\Models\LiveChatModerator;
use App\Features\LiveChat\Models\LiveMessage;
use App\Features\Users\Models\User;

final class LiveMessagePolicy
{
    public function delete(User $user, LiveMessage $message): bool
    {
        $room = $message->room()->first();

        if ($room === null) {
            return false;
        }

        if ($room->host_id === $user->getKey()) {
            return true;
        }

        return LiveChatModerator::query()
            ->where('room_id', $room->getKey())
            ->where('user_id', $user->getKey())
            ->exists();
    }
}
