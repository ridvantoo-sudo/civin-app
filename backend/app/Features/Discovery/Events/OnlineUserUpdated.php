<?php

namespace App\Features\Discovery\Events;

use App\Features\Profiles\Http\Resources\SocialUserResource;
use App\Features\Users\Models\User;
use App\Features\UserStatus\Models\UserStatus;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class OnlineUserUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly UserStatus $status) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('discovery.online')];
    }

    public function broadcastAs(): string
    {
        return 'online.user.updated';
    }

    public function broadcastWith(): array
    {
        $user = User::query()
            ->with('profile.country', 'socialStatus')
            ->find($this->status->user_id);

        return [
            'user_id' => $this->status->user_id,
            'is_online' => (bool) $this->status->is_online,
            'is_live' => (bool) $this->status->is_live,
            'last_seen_at' => $this->status->last_seen_at?->toISOString(),
            'user' => $user === null
                ? null
                : (new SocialUserResource($user))->resolve(),
        ];
    }
}
