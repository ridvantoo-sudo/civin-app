<?php

namespace App\Features\Games\Plugins\LuckyNumber\Events;

use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberDraw;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberTicket;
use App\Features\Users\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LuckyNumberDrawStarted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly LuckyNumberDraw $draw,
        public readonly ?LuckyNumberTicket $ticket = null,
        public readonly ?User $user = null,
    ) {}

    public function broadcastOn(): array
    {
        $userId = $this->resolveUserId();
        if ($userId === null) {
            return [];
        }

        return [
            new PrivateChannel('user.games.'.$userId),
            new PrivateChannel('user.lucky-number.'.$userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'lucky_number.draw_started';
    }

    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->draw->id,
            'draw_id' => $this->draw->id,
            'ticket_id' => $this->ticket?->id,
            'user_id' => $this->resolveUserId(),
            'status' => $this->draw->status,
            'game_mode' => $this->draw->game_mode ?? $this->ticket?->game_mode,
            'draw_type' => $this->draw->draw_type,
            'started_at' => $this->draw->started_at?->toISOString() ?? now()->toISOString(),
        ];
    }

    private function resolveUserId(): ?string
    {
        return $this->ticket?->user_id
            ?? $this->user?->getKey()
            ?? $this->draw->triggered_by_user_id;
    }
}
