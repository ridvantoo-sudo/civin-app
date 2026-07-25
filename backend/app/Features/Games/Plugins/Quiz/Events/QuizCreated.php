<?php

namespace App\Features\Games\Plugins\Quiz\Events;

use App\Features\Games\Plugins\Quiz\Models\QuizSession;
use App\Features\Games\Plugins\Quiz\Support\QuizBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class QuizCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly QuizSession $session) {}

    public function broadcastOn(): array
    {
        return QuizBroadcast::channelsFor($this->session);
    }

    public function broadcastAs(): string
    {
        return 'quiz.created';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return QuizBroadcast::sessionPayload($this->session);
    }
}
