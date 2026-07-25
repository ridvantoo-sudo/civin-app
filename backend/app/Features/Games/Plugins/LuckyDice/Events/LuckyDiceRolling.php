<?php

namespace App\Features\Games\Plugins\LuckyDice\Events;

use App\Features\Games\Plugins\LuckyDice\Models\LuckyDiceBet;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LuckyDiceRolling implements ShouldBroadcast, ShouldDispatchAfterCommit
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
        return 'lucky_dice.rolling';
    }

    public function broadcastWith(): array
    {
        $result = $this->bet->result;

        return [
            'event_id' => $this->bet->id.'-rolling',
            'bet_id' => $this->bet->id,
            'user_id' => $this->bet->user_id,
            'idempotency_key' => $this->bet->idempotency_key,
            'status' => $this->bet->status,
            'dice_1' => $result?->dice_1 ?? $this->bet->outcome['dice_1'] ?? null,
            'dice_2' => $result?->dice_2 ?? $this->bet->outcome['dice_2'] ?? null,
            'dice_3' => $result?->dice_3 ?? $this->bet->outcome['dice_3'] ?? null,
            'total' => $result?->total ?? $this->bet->outcome['total'] ?? null,
            'is_triple' => $result?->is_triple ?? $this->bet->outcome['is_triple'] ?? false,
        ];
    }
}
