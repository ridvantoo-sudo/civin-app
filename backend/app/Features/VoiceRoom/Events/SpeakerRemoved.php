<?php

namespace App\Features\VoiceRoom\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SpeakerRemoved implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $roomId,
        public readonly string $userId,
        public readonly int $seatIndex,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("voice.room.{$this->roomId}")];
    }

    public function broadcastAs(): string
    {
        return 'speaker.removed';
    }

    public function broadcastWith(): array
    {
        return [
            'room_id' => $this->roomId,
            'user_id' => $this->userId,
            'seat_index' => $this->seatIndex,
        ];
    }
}
