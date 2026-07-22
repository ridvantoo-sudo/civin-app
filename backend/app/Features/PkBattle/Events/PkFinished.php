<?php

namespace App\Features\PkBattle\Events;

use App\Features\PkBattle\Http\Resources\PkBattleResource;
use App\Features\PkBattle\Models\PkBattle;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PkFinished implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly PkBattle $battle) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("live.room.{$this->battle->room_a_id}"),
            new PrivateChannel("live.room.{$this->battle->room_b_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'pk.finished';
    }

    public function broadcastWith(): array
    {
        return [
            'battle' => (new PkBattleResource($this->battle))->resolve(),
        ];
    }
}
