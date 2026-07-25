<?php

namespace App\Features\Games\Plugins\SlotMachine\Events;

use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineSpin;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SlotSpinStarted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly SlotMachineSpin $spin) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.games.'.$this->spin->user_id),
            new PrivateChannel('user.slot-machine.'.$this->spin->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'slot_machine.started';
    }

    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->spin->id,
            'spin_id' => $this->spin->id,
            'user_id' => $this->spin->user_id,
            'idempotency_key' => $this->spin->idempotency_key,
            'status' => $this->spin->status,
            'game_mode' => $this->spin->game_mode,
            'cost_coins' => $this->spin->cost_coins,
            'reward_coins' => $this->spin->reward_coins,
            'is_win' => $this->spin->is_win,
            'is_jackpot' => $this->spin->is_jackpot,
            'is_bonus' => $this->spin->is_bonus,
            'created_at' => $this->spin->created_at?->toISOString(),
        ];
    }
}
