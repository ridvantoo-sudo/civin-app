<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Events;

use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMove;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsRound;
use App\Features\Games\Plugins\RockPaperScissors\Support\RpsBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Announces that a player has locked in, never what they picked.
 */
final class RpsMoveSubmitted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly RpsMatch $match,
        public readonly RpsRound $round,
        public readonly RpsMove $move,
    ) {}

    public function broadcastOn(): array
    {
        return RpsBroadcast::channelsFor($this->match);
    }

    public function broadcastAs(): string
    {
        return 'rps.move.submitted';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return RpsBroadcast::movePayload($this->match, $this->round, $this->move);
    }
}
