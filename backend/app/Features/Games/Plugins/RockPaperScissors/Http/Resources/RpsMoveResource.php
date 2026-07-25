<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Http\Resources;

use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMove;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RpsMove
 *
 * The move value is echoed back to the player who submitted it, and to anyone
 * else only after the round has been revealed.
 */
final class RpsMoveResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isOwner = $user !== null && (string) $this->user_id === (string) $user->getKey();
        $canSeeMove = $isOwner || $this->isRevealed();

        return [
            'id' => $this->id,
            'match_id' => $this->match_id,
            'round_id' => $this->round_id,
            'user_id' => $this->user_id,
            'role' => $this->role,
            'round_index' => (int) $this->round_index,
            'is_auto' => (bool) $this->is_auto,
            'timed_out' => (bool) $this->timed_out,
            'response_ms' => $this->response_ms,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'revealed_at' => $this->revealed_at?->toISOString(),
            'move' => $this->when($canSeeMove, $this->move),
        ];
    }
}
