<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Events;

use App\Features\Games\Plugins\RockPaperScissors\Models\RpsTournament;
use App\Features\Games\Plugins\RockPaperScissors\Support\RpsBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TournamentStarted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly RpsTournament $tournament) {}

    public function broadcastOn(): array
    {
        return RpsBroadcast::tournamentChannels($this->tournament);
    }

    public function broadcastAs(): string
    {
        return 'rps.tournament.started';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return RpsBroadcast::tournamentPayload($this->tournament);
    }
}
