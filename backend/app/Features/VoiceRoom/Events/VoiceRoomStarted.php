<?php

namespace App\Features\VoiceRoom\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class VoiceRoomStarted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $roomId,
        public readonly string $hostId,
        public readonly int $seatCount,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("voice.room.{$this->roomId}")];
    }

    public function broadcastAs(): string
    {
        return 'voice.room.started';
    }

    public function broadcastWith(): array
    {
        return [
            'room_id' => $this->roomId,
            'host_id' => $this->hostId,
            'seat_count' => $this->seatCount,
            'participant_count' => 1,
        ];
    }
}
