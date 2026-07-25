<?php

namespace App\Features\Games\Plugins\LuckyNumber\Events;

use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberTicket;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LuckyNumberTicketPurchased implements ShouldBroadcast, ShouldDispatchAfterCommit
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
        return 'lucky_number.ticket_purchased';
    }

    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->ticket->id,
            'ticket_id' => $this->ticket->id,
            'user_id' => $this->ticket->user_id,
            'idempotency_key' => $this->ticket->idempotency_key,
            'status' => $this->ticket->status,
            'game_mode' => $this->ticket->game_mode,
            'bet_amount' => $this->ticket->bet_amount,
            'cost_coins' => $this->ticket->cost_coins,
            'picked_numbers' => $this->ticket->picked_numbers,
            'auto_pick' => $this->ticket->auto_pick,
            'created_at' => $this->ticket->created_at?->toISOString(),
        ];
    }
}
