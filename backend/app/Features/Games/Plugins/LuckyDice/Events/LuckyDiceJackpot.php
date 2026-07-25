<?php

namespace App\Features\Games\Plugins\LuckyDice\Events;

use App\Features\Games\Plugins\LuckyDice\Models\LuckyDiceBet;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LuckyDiceJackpot implements ShouldBroadcast, ShouldDispatchAfterCommit
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
        return 'lucky_dice.jackpot';
    }

    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->bet->id.'-jackpot',
            'bet_id' => $this->bet->id,
            'user_id' => $this->bet->user_id,
            'idempotency_key' => $this->bet->idempotency_key,
            'reward_type' => $this->bet->reward_type,
            'reward_amount' => $this->bet->reward_amount,
            'outcome' => $this->bet->outcome,
        ];
    }
}
