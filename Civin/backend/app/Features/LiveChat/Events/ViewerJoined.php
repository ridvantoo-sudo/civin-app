<?php

namespace App\Features\LiveChat\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ViewerJoined implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $roomId,
        public readonly string $viewerId,
        public readonly int $viewerCount,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("live.room.{$this->roomId}")];
    }

    public function broadcastAs(): string
    {
        return 'viewer.joined';
    }

    public function broadcastWith(): array
    {
        return [
            'room_id' => $this->roomId,
            'viewer_id' => $this->viewerId,
            'viewer_count' => $this->viewerCount,
        ];
    }
}
