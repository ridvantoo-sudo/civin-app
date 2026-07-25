<?php

namespace App\Features\Games\Events;

use App\Features\Games\Models\GameMatch;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class GameMatchUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly GameMatch $match) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('game.match.'.$this->match->id)];
    }

    public function broadcastAs(): string
    {
        return 'game.match.updated';
    }

    public function broadcastWith(): array
    {
        $this->match->loadMissing(['game', 'players.user.profile', 'host', 'winner']);

        return [
            'match_id' => $this->match->id,
            'game_id' => $this->match->game_id,
            'game_slug' => $this->match->game?->slug,
            'host_user_id' => $this->match->host_user_id,
            'winner_user_id' => $this->match->winner_user_id,
            'max_players' => $this->match->max_players,
            'stake_coins' => $this->match->stake_coins,
            'status' => $this->match->status,
            'state' => $this->match->state,
            'started_at' => $this->match->started_at?->toISOString(),
            'finished_at' => $this->match->finished_at?->toISOString(),
            'players' => $this->match->players->map(fn ($player): array => [
                'user_id' => $player->user_id,
                'seat' => $player->seat,
                'status' => $player->status,
                'score' => $player->score,
                'username' => $player->user?->username,
                'display_name' => $player->user?->profile?->display_name ?? $player->user?->username,
                'avatar_url' => $player->user?->profile?->avatar_url,
            ])->values()->all(),
        ];
    }
}
