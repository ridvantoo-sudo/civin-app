<?php

namespace App\Features\Levels\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LevelUp implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $userId,
        public readonly int $fromLevel,
        public readonly int $toLevel,
        public readonly int $xpTotal,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("users.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'level.up';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'from_level' => $this->fromLevel,
            'to_level' => $this->toLevel,
            'xp_total' => $this->xpTotal,
        ];
    }
}
