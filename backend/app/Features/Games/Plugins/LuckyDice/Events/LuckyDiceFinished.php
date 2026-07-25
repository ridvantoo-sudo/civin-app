<?php

namespace App\Features\Games\Plugins\LuckyDice\Events;

use App\Features\Games\Plugins\LuckyDice\Models\LuckyDiceBet;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LuckyDiceFinished implements ShouldBroadcast, ShouldDispatchAfterCommit
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
        return 'lucky_dice.finished';
    }

    public function broadcastWith(): array
    {
        $result = $this->bet->relationLoaded('result') ? $this->bet->result : $this->bet->result()->first();

        return [
            'event_id' => $this->bet->id,
            'bet_id' => $this->bet->id,
            'user_id' => $this->bet->user_id,
            'idempotency_key' => $this->bet->idempotency_key,
            'status' => $this->bet->status,
            'bet_mode' => $this->bet->bet_mode,
            'prediction' => $this->bet->prediction,
            'bet_amount' => $this->bet->bet_amount,
            'reward_type' => $this->bet->reward_type,
            'reward_amount' => $this->bet->reward_amount,
            'reward_currency' => $this->bet->reward_currency,
            'is_jackpot' => $this->bet->is_jackpot,
            'is_win' => $this->bet->is_win,
            'multiplier' => $this->bet->multiplier,
            'dice_1' => $result?->dice_1,
            'dice_2' => $result?->dice_2,
            'dice_3' => $result?->dice_3,
            'total' => $result?->total,
            'is_triple' => $result?->is_triple,
            'outcome' => $this->bet->outcome,
            'settled_at' => $this->bet->settled_at?->toISOString(),
        ];
    }
}
