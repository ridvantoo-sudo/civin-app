<?php

namespace App\Features\Games\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class LeaderboardEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $row = is_array($this->resource) ? $this->resource : (array) $this->resource;

        return [
            'rank' => $row['rank'] ?? null,
            'user_id' => $row['user_id'] ?? null,
            'username' => $row['username'] ?? null,
            'display_name' => $row['display_name'] ?? null,
            'avatar_url' => $row['avatar_url'] ?? null,
            'game_id' => $row['game_id'] ?? null,
            'game_slug' => $row['game_slug'] ?? null,
            'plays' => $row['plays'] ?? 0,
            'wins' => $row['wins'] ?? 0,
            'coins_wagered' => $row['coins_wagered'] ?? 0,
            'coins_net' => $row['coins_net'] ?? 0,
            'score' => $row['score'] ?? 0,
            'period' => $row['period'] ?? null,
            'period_key' => $row['period_key'] ?? null,
        ];
    }
}
