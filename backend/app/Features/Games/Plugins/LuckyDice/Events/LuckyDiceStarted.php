<?php

namespace App\Features\Games\Plugins\LuckyDice\Events;

use App\Features\Games\Plugins\LuckyDice\Models\LuckyDiceBet;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LuckyDiceStarted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly LuckyDiceBet $bet) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.games.'.$this->bet->user_id),
            new PrivateChannel('user.lucky-dice.'.$this->bet->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'lucky_dice.started';
    }

    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->bet->id,
            'bet_id' => $this->bet->id,
            'user_id' => $this->bet->user_id,
            'idempotency_key' => $this->bet->idempotency_key,
            'status' => $this->bet->status,
            'bet_amount' => $this->bet->bet_amount,
            'bet_mode' => $this->bet->bet_mode,
            'created_at' => $this->bet->created_at?->toISOString(),
        ];
    }
}
