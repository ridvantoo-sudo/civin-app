<?php

namespace App\Features\Discovery\Listeners;

use App\Features\Discovery\Events\OnlineUserUpdated;
use App\Features\UserStatus\Events\UserStatusChanged;

final class BroadcastOnlineUserUpdated
{
    public function handle(UserStatusChanged $event): void
    {
        OnlineUserUpdated::dispatch($event->status);
    }
}
