<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Http\Resources;

use App\Features\Games\Plugins\RockPaperScissors\Models\RpsBracket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RpsBracket */
final class RpsBracketResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tournament_id' => $this->tournament_id,
            'match_id' => $this->match_id,
            'bracket_type' => $this->bracket_type,
            'round_index' => (int) $this->round_index,
            'slot_index' => (int) $this->slot_index,
            'status' => $this->status,
            'player_one_id' => $this->player_one_id,
            'player_two_id' => $this->player_two_id,
            'winner_id' => $this->winner_id,
            'loser_id' => $this->loser_id,
            'next_bracket_id' => $this->next_bracket_id,
            'next_loser_bracket_id' => $this->next_loser_bracket_id,
            'is_bye' => (bool) $this->is_bye,
        ];
    }
}
