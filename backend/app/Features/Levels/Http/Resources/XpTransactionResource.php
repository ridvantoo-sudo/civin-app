<?php

namespace App\Features\Levels\Http\Resources;

use App\Features\Levels\Models\XpTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin XpTransaction */
final class XpTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'amount' => (int) $this->amount,
            'level_before' => (int) $this->level_before,
            'level_after' => (int) $this->level_after,
            'xp_total_after' => (int) $this->xp_total_after,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
