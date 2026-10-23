<?php

namespace App\Features\Followers\Notifications;

use App\Features\Followers\Models\Follow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class FollowRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Follow $follow)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'follow_id' => $this->follow->getKey(),
            'follower_id' => $this->follow->follower_id,
            'type' => 'follow_request',
        ];
    }
}
