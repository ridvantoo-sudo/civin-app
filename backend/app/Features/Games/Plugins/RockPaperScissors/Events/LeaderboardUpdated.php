<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Events;

use App\Features\Games\Plugins\RockPaperScissors\Support\RpsBroadcast;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LeaderboardUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /** @param list<array<string, mixed>> $entries */
    public function __construct(
        public readonly string $period,
        public readonly array $entries = [],
        public readonly ?string $userId = null,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('rps.leaderboard')];
        if ($this->userId !== null) {
            $channels[] = new PrivateChannel('user.rps.'.$this->userId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'rps.leaderboard.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return RpsBroadcast::leaderboardPayload($this->period, $this->entries);
    }
}
