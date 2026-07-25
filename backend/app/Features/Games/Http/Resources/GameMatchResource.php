<?php

namespace App\Features\Games\Http\Resources;

use App\Features\Games\Models\GameMatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GameMatch */
final class GameMatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'game_id' => $this->game_id,
            'game_slug' => $this->whenLoaded('game', fn () => $this->game?->slug),
            'host_user_id' => $this->host_user_id,
            'winner_user_id' => $this->winner_user_id,
            'max_players' => $this->max_players,
            'stake_coins' => $this->stake_coins,
            'status' => $this->status,
            'state' => $this->state,
            'metadata' => $this->metadata,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'players' => $this->whenLoaded('players', function () {
                return $this->players->map(fn ($player): array => [
                    'id' => $player->id,
                    'user_id' => $player->user_id,
                    'seat' => $player->seat,
                    'status' => $player->status,
                    'score' => $player->score,
                    'state' => $player->state,
                    'username' => $player->user?->username,
                    'display_name' => $player->user?->profile?->display_name ?? $player->user?->username,
                    'avatar_url' => $player->user?->profile?->avatar_url,
                ])->values()->all();
            }),
        ];
    }
}
