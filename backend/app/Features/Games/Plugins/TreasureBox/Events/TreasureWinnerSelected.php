<?php

namespace App\Features\Games\Plugins\TreasureBox\Events;

use App\Features\Games\Models\TreasureBox;
use App\Features\Games\Models\TreasureBoxWinner;
use App\Features\Games\Plugins\TreasureBox\Support\TreasureBoxBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TreasureWinnerSelected implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly TreasureBox $treasure,
        public readonly TreasureBoxWinner $winner,
    ) {}

    public function broadcastOn(): array
    {
        return TreasureBoxBroadcast::channelsFor($this->treasure, $this->winner->user_id);
    }

    public function broadcastAs(): string
    {
        return 'treasure.winner_selected';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return TreasureBoxBroadcast::winnerPayload($this->treasure, $this->winner);
    }
}
