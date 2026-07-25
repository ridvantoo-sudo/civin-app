<?php

namespace App\Features\VoicePkBattle\Events;

use App\Features\VoicePkBattle\Models\VoicePkBattle;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class VoicePkBattleBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly VoicePkBattle $battle) {}

    public function broadcastOn(): array
    {
        return $this->battle->teams
            ->map(fn ($team) => new PrivateChannel("voice.room.{$team->room_id}"))
            ->all();
    }

    public function broadcastWith(): array
    {
        return ['battle' => $this->battle->toArray()];
    }
}
