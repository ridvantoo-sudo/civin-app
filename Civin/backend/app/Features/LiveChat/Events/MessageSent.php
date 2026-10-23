<?php

namespace App\Features\LiveChat\Events;

use App\Features\LiveChat\Models\LiveMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MessageSent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly LiveMessage $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("live.room.{$this->message->room_id}")];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        $message = $this->message->loadMissing('user.profile', 'user.socialStatus');
        $user = $message->user;
        $profile = $user?->profile;

        return [
            'room_id' => $message->room_id,
            'message' => [
                'id' => $message->id,
                'room_id' => $message->room_id,
                'message' => $message->message,
                'type' => $message->type,
                'metadata' => $message->metadata,
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'username' => $user->username,
                    'nickname' => $profile?->display_name,
                    'avatar_url' => $profile?->avatar_url,
                    'cover_image_url' => $profile?->cover_image_url,
                    'bio' => $profile?->bio,
                    'country' => $profile?->relationLoaded('country') ? $profile->country : null,
                    'level' => $profile?->level,
                    'is_vip' => (bool) $profile?->is_vip,
                    'is_private' => (bool) $profile?->is_private,
                    'followers_count' => $profile?->followers_count ?? 0,
                    'following_count' => $profile?->following_count ?? 0,
                    'likes_count' => $profile?->likes_count ?? 0,
                    'is_online' => (bool) $user->socialStatus?->is_online,
                    'is_live' => (bool) $user->socialStatus?->is_live,
                ],
                'created_at' => $message->created_at?->toISOString(),
                'updated_at' => $message->updated_at?->toISOString(),
            ],
        ];
    }
}
