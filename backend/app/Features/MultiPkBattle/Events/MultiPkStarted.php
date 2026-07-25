<?php

namespace App\Features\MultiPkBattle\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MultiPkStarted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use BroadcastsMultiPkBattle, Dispatchable, SerializesModels;

    public function broadcastAs(): string
    {
        return 'multi-pk.started';
    }
}
