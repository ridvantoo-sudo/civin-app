<?php

namespace App\Features\Ai\Http\Resources;

use App\Features\Ai\Models\AiJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AiJob */
final class AiJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'provider' => $this->provider,
            'input' => $this->input,
            'result' => $this->result,
            'error' => $this->error,
            'moderation' => $this->whenLoaded('moderationLog', fn () => $this->moderationLog === null ? null : [
                'verdict' => $this->moderationLog->verdict,
                'labels' => $this->moderationLog->labels,
                'confidence' => $this->moderationLog->confidence,
                'surface' => $this->moderationLog->surface,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
