<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Events;

use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;
use App\Features\Games\Plugins\RockPaperScissors\Support\RpsBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class RpsChallengeCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly RpsMatch $match) {}

    public function broadcastOn(): array
    {
        return RpsBroadcast::channelsFor($this->match);
    }

    public function broadcastAs(): string
    {
        return 'rps.challenge.created';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return RpsBroadcast::matchPayload($this->match);
    }
}
