<?php

namespace App\Features\Agency\Http\Resources;

use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Features\Agency\Models\Agency */
final class AgencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $owner = $this->relationLoaded('owner') ? $this->owner : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'logo' => $this->logo,
            'commission_rate' => (float) $this->commission_rate,
            'status' => $this->status,
            'members_count' => $this->members_count,
            'hosts_count' => $this->hosts_count,
            'total_gross_earnings' => $this->total_gross_earnings,
            'total_commission' => $this->total_commission,
            'owner' => $owner === null ? null : new SocialUserResource($owner),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
