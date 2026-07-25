<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Events;

use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsRound;
use App\Features\Games\Plugins\RockPaperScissors\Support\RpsBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The only event that carries move values, and only because the round has
 * already been resolved server side.
 */
final class RpsRoundFinished implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly RpsMatch $match,
        public readonly RpsRound $round,
    ) {}

    public function broadcastOn(): array
    {
        return RpsBroadcast::channelsFor($this->match);
    }

    public function broadcastAs(): string
    {
        return 'rps.round.finished';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return RpsBroadcast::roundPayload($this->match, $this->round);
    }
}
