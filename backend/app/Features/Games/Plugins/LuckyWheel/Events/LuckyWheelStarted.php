<?php

namespace App\Features\Games\Plugins\LuckyWheel\Events;

use App\Features\Games\Plugins\LuckyWheel\Models\LuckyWheelSpin;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LuckyWheelStarted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly LuckyWheelSpin $spin) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.games.'.$this->spin->user_id),
            new PrivateChannel('user.lucky-wheel.'.$this->spin->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'lucky_wheel.started';
    }

    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->spin->id,
            'spin_id' => $this->spin->id,
            'user_id' => $this->spin->user_id,
            'idempotency_key' => $this->spin->idempotency_key,
            'status' => $this->spin->status,
            'cost_coins' => $this->spin->cost_coins,
            'created_at' => $this->spin->created_at?->toISOString(),
        ];
    }
}
