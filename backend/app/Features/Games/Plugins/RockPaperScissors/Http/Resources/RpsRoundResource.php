<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Http\Resources;

use App\Features\Games\Plugins\RockPaperScissors\Models\RpsRound;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RpsRound
 *
 * Move values are attached only once the round has been resolved and revealed.
 */
final class RpsRoundResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $payload = [
            'id' => $this->id,
            'match_id' => $this->match_id,
            'round_index' => (int) $this->round_index,
            'status' => $this->status,
            'challenger_submitted' => (bool) $this->challenger_submitted,
            'opponent_submitted' => (bool) $this->opponent_submitted,
            'revealed' => (bool) $this->revealed,
            'started_at' => $this->started_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
        ];

        if ($this->revealed) {
            $payload += [
                'result' => $this->result,
                'winner_id' => $this->winner_id,
                'challenger_move' => $this->challenger_move,
                'opponent_move' => $this->opponent_move,
                'resolved_at' => $this->resolved_at?->toISOString(),
                'revealed_at' => $this->revealed_at?->toISOString(),
            ];
        }

        return $payload;
    }
}
