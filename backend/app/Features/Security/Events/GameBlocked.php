<?php

namespace App\Features\Security\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Advisory notice that game access is restricted for an account. The Game
 * Engine is untouched; it may consult the security API if it chooses to.
 */
final class GameBlocked implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $userId,
        public readonly string $reason,
        public readonly ?string $gameSlug = null,
        public readonly ?string $banId = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel((string) config('security_platform.realtime.channel', 'security.monitor')),
            new PrivateChannel('user.security.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'security.game_blocked';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'reason' => $this->reason,
            'game_slug' => $this->gameSlug,
            'ban_id' => $this->banId,
        ];
    }
}
