<?php

namespace App\Features\Games\Plugins\Quiz\Events;

use App\Features\Games\Plugins\Quiz\Models\QuizPlayer;
use App\Features\Games\Plugins\Quiz\Models\QuizSession;
use App\Features\Games\Plugins\Quiz\Support\QuizBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class QuizJoined implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly QuizSession $session,
        public readonly QuizPlayer $player,
    ) {}

    public function broadcastOn(): array
    {
        return QuizBroadcast::channelsFor($this->session, (string) $this->player->user_id);
    }

    public function broadcastAs(): string
    {
        return 'quiz.joined';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return array_merge(QuizBroadcast::sessionPayload($this->session), [
            'event_id' => $this->player->id,
            'player_id' => $this->player->id,
            'joiner_id' => $this->player->user_id,
            'team_id' => $this->player->team_id,
            'joined_at' => $this->player->joined_at?->toISOString(),
        ]);
    }
}
