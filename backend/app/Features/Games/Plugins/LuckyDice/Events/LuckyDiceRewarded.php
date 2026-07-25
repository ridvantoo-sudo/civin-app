<?php

namespace App\Features\Games\Plugins\LuckyDice\Events;

use App\Features\Games\Plugins\LuckyDice\Models\LuckyDiceBet;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LuckyDiceRewarded implements ShouldBroadcast, ShouldDispatchAfterCommit
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
        return 'lucky_dice.rewarded';
    }

    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->bet->id.'-reward',
            'bet_id' => $this->bet->id,
            'user_id' => $this->bet->user_id,
            'idempotency_key' => $this->bet->idempotency_key,
            'reward_type' => $this->bet->reward_type,
            'reward_amount' => $this->bet->reward_amount,
            'reward_currency' => $this->bet->reward_currency,
            'reward_reference' => $this->bet->reward_reference,
            'reward_id' => $this->bet->reward_id,
            'is_jackpot' => $this->bet->is_jackpot,
            'status' => $this->bet->status,
        ];
    }
}
