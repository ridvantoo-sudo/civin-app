<?php

namespace App\Features\Games\Plugins\LuckyWheel\Events;

use App\Features\Games\Plugins\LuckyWheel\Models\LuckyWheelSpin;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LuckyWheelFinished implements ShouldBroadcast, ShouldDispatchAfterCommit
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
        return 'lucky_wheel.finished';
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
            'reward_type' => $this->spin->reward_type,
            'reward_amount' => $this->spin->reward_amount,
            'reward_currency' => $this->spin->reward_currency,
            'is_jackpot' => $this->spin->is_jackpot,
            'sector_position' => $this->spin->sector_position,
            'sector_label' => $this->spin->sector_label,
            'outcome' => $this->spin->outcome,
            'settled_at' => $this->spin->settled_at?->toISOString(),
        ];
    }
}
