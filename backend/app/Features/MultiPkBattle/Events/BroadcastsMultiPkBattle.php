<?php

namespace App\Features\MultiPkBattle\Events;

use App\Features\MultiPkBattle\Models\MultiPkBattle;
use Illuminate\Broadcasting\PrivateChannel;

trait BroadcastsMultiPkBattle
{
    public function __construct(public readonly MultiPkBattle $battle) {}

    public function broadcastOn(): array
    {
        return $this->battle->participants
            ->map(fn ($participant) => new PrivateChannel("live.room.{$participant->room_id}"))
            ->all();
    }

    public function broadcastWith(): array
    {
        return ['battle' => $this->battle->toArray()];
    }
}
