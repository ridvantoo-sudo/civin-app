<?php

namespace App\Features\Games\Plugins\Quiz\Events;

use App\Features\Games\Plugins\Quiz\Models\QuizAnswer;
use App\Features\Games\Plugins\Quiz\Models\QuizSession;
use App\Features\Games\Plugins\Quiz\Support\QuizBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class AnswerSubmitted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly QuizSession $session,
        public readonly QuizAnswer $answer,
    ) {}

    public function broadcastOn(): array
    {
        return QuizBroadcast::channelsFor($this->session, (string) $this->answer->user_id);
    }

    public function broadcastAs(): string
    {
        return 'quiz.answer.submitted';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return QuizBroadcast::answerPayload($this->session, $this->answer);
    }
}
