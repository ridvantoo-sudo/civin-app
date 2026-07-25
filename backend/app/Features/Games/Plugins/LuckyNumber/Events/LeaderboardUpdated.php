<?php

namespace App\Features\Games\Plugins\LuckyNumber\Events;

use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberTicket;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LeaderboardUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly LuckyNumberTicket $ticket) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.games.'.$this->ticket->user_id),
            new PrivateChannel('user.lucky-number.'.$this->ticket->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'lucky_number.leaderboard_updated';
    }

    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->ticket->id,
            'ticket_id' => $this->ticket->id,
            'user_id' => $this->ticket->user_id,
            'reward_coins' => $this->ticket->reward_coins,
            'is_win' => $this->ticket->is_win,
            'is_jackpot' => $this->ticket->is_jackpot,
            'created_at' => $this->ticket->created_at?->toISOString(),
        ];
    }
}
