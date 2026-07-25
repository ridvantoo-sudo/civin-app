<?php

namespace App\Features\Games\Http\Resources;

use App\Features\Games\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Game */
final class GameResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'category' => $this->category,
            'min_bet_coins' => $this->min_bet_coins,
            'max_bet_coins' => $this->max_bet_coins,
            'house_edge_bps' => $this->house_edge_bps,
            'supports_multiplayer' => (bool) $this->supports_multiplayer,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'config' => $this->config,
        ];
    }
}
