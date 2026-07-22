<?php

namespace App\Features\Agency\Http\Resources;

use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Features\Agency\Models\AgencyCommission */
final class AgencyCommissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $host = $this->relationLoaded('host') ? $this->host : null;

        return [
            'id' => $this->id,
            'agency_id' => $this->agency_id,
            'gross_amount' => $this->gross_amount,
            'commission_rate' => (float) $this->commission_rate,
            'commission_amount' => $this->commission_amount,
            'host_net_amount' => $this->host_net_amount,
            'currency' => $this->currency,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'metadata' => $this->metadata,
            'host' => $host === null ? null : new SocialUserResource($host),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
