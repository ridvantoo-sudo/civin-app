<?php

namespace App\Features\Games\Plugins\TreasureBox\Events;

use App\Features\Games\Models\TreasureBox;
use App\Features\Games\Plugins\TreasureBox\Support\TreasureBoxBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TreasureExpired implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly TreasureBox $treasure,
        public readonly int $refundedAmount = 0,
    ) {}

    public function broadcastOn(): array
    {
        return TreasureBoxBroadcast::channelsFor($this->treasure);
    }

    public function broadcastAs(): string
    {
        return 'treasure.expired';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return array_merge(TreasureBoxBroadcast::treasurePayload($this->treasure), [
            'refunded_amount' => $this->refundedAmount,
        ]);
    }
}
