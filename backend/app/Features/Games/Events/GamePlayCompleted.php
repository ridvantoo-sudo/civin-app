<?php

namespace App\Features\Games\Events;

use App\Features\Games\Models\GamePlay;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class GamePlayCompleted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly GamePlay $play) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.games.'.$this->play->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'game.play.completed';
    }

    public function broadcastWith(): array
    {
        $this->play->loadMissing('game');

        return [
            'play_id' => $this->play->id,
            'user_id' => $this->play->user_id,
            'game_id' => $this->play->game_id,
            'game_slug' => $this->play->game?->slug,
            'bet_coins' => $this->play->bet_coins,
            'reward_coins' => $this->play->reward_coins,
            'reward_diamonds' => $this->play->reward_diamonds,
            'result' => $this->play->result,
            'outcome' => $this->play->outcome,
            'created_at' => $this->play->created_at?->toISOString(),
        ];
    }
}
