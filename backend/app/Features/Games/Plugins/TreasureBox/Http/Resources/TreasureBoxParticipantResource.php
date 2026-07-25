<?php

namespace App\Features\Games\Plugins\TreasureBox\Http\Resources;

use App\Features\Games\Models\TreasureBoxParticipant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TreasureBoxParticipant */
final class TreasureBoxParticipantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'treasure_box_id' => $this->treasure_box_id,
            'user_id' => $this->user_id,
            'idempotency_key' => $this->idempotency_key,
            'metadata' => $this->metadata,
            'joined_at' => $this->joined_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
