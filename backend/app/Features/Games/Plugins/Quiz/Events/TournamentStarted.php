<?php

namespace App\Features\Games\Plugins\Quiz\Events;

use App\Features\Games\Plugins\Quiz\Models\QuizTournament;
use App\Features\Games\Plugins\Quiz\Support\QuizBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TournamentStarted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly QuizTournament $tournament) {}

    public function broadcastOn(): array
    {
        return [];
    }

    public function broadcastAs(): string
    {
        return 'quiz.tournament.started';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return QuizBroadcast::tournamentPayload($this->tournament);
    }
}
