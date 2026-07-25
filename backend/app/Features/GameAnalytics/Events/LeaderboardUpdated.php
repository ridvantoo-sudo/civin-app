<?php

namespace App\Features\GameAnalytics\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LeaderboardUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public readonly array $payload = []) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel((string) config('game_analytics.realtime.channel', 'analytics.dashboard'))];
    }

    public function broadcastAs(): string
    {
        return 'leaderboard.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
