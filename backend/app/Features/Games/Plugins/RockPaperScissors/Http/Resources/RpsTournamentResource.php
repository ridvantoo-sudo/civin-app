<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Http\Resources;

use App\Features\Games\Plugins\RockPaperScissors\Models\RpsTournament;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RpsTournament */
final class RpsTournamentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'format' => $this->format,
            'status' => $this->status,
            'mode' => $this->mode,
            'rounds' => (int) $this->rounds,
            'current_round' => (int) $this->current_round,
            'max_players' => (int) $this->max_players,
            'min_players' => (int) $this->min_players,
            'registered_count' => (int) $this->registered_count,
            'entry_fee_coins' => (int) $this->entry_fee_coins,
            'reward_type' => $this->reward_type,
            'reward_pool' => (int) $this->reward_pool,
            'prize_structure' => $this->prize_structure,
            'is_vip' => (bool) $this->is_vip,
            'is_daily' => (bool) $this->is_daily,
            'is_scheduled' => (bool) $this->is_scheduled,
            'brackets_generated' => (bool) $this->brackets_generated,
            'winner_id' => $this->winner_id,
            'joined' => $user !== null && $this->hasParticipant((string) $user->getKey()),
            'registration_opens_at' => $this->registration_opens_at?->toISOString(),
            'registration_closes_at' => $this->registration_closes_at?->toISOString(),
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'brackets' => RpsBracketResource::collection($this->whenLoaded('brackets')),
        ];
    }
}
