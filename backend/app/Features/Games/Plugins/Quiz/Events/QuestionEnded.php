<?php

namespace App\Features\Games\Plugins\Quiz\Events;

use App\Features\Games\Plugins\Quiz\Models\QuizSession;
use App\Features\Games\Plugins\Quiz\Support\QuizBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class QuestionEnded implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>|null  $question
     */
    public function __construct(
        public readonly QuizSession $session,
        public readonly ?array $question = null,
    ) {}

    public function broadcastOn(): array
    {
        return QuizBroadcast::channelsFor($this->session);
    }

    public function broadcastAs(): string
    {
        return 'quiz.question.ended';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return QuizBroadcast::questionPayload($this->session, $this->question, true);
    }
}
