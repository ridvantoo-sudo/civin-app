<?php

namespace App\Features\PkBattle\Events;

use App\Features\PkBattle\Http\Resources\PkBattleResource;
use App\Features\PkBattle\Http\Resources\PkScoreResource;
use App\Features\PkBattle\Models\PkBattle;
use App\Features\PkBattle\Models\PkScore;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PkScoreUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly PkBattle $battle,
        public readonly PkScore $score,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("live.room.{$this->battle->room_a_id}"),
            new PrivateChannel("live.room.{$this->battle->room_b_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'pk.score.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'battle_id' => $this->battle->id,
            'score' => (new PkScoreResource($this->score))->resolve(),
            'battle' => (new PkBattleResource($this->battle))->resolve(),
        ];
    }
}
