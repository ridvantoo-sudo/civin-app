<?php

namespace App\Features\VoiceRoom\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SeatUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $roomId,
        public readonly int $seatIndex,
        public readonly string $status,
        public readonly ?string $userId,
        public readonly bool $isMuted,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("voice.room.{$this->roomId}")];
    }

    public function broadcastAs(): string
    {
        return 'seat.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'room_id' => $this->roomId,
            'seat_index' => $this->seatIndex,
            'status' => $this->status,
            'user_id' => $this->userId,
            'is_muted' => $this->isMuted,
        ];
    }
}
