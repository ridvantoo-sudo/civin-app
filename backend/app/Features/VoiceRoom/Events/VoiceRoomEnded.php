<?php

namespace App\Features\VoiceRoom\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class VoiceRoomEnded implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $roomId,
        public readonly string $hostId,
        public readonly int $durationSeconds,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("voice.room.{$this->roomId}")];
    }

    public function broadcastAs(): string
    {
        return 'voice.room.ended';
    }

    public function broadcastWith(): array
    {
        return [
            'room_id' => $this->roomId,
            'host_id' => $this->hostId,
            'participant_count' => 0,
            'duration' => $this->durationSeconds,
        ];
    }
}
