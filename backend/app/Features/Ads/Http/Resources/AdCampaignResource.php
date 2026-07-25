<?php

namespace App\Features\Ads\Http\Resources;

use App\Features\Ads\Models\AdCampaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AdCampaign */
final class AdCampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $includeOwnerFields = $request->user()?->getKey() === $this->owner_user_id
            || (bool) $request->user()?->is_admin;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'placement' => $this->placement,
            'media_url' => $this->media_url,
            'media_type' => $this->media_type,
            'cta_label' => $this->cta_label,
            'cta_url' => $this->cta_url,
            'target_type' => $this->target_type,
            'target_payload' => $this->when($includeOwnerFields, $this->target_payload),
            'status' => $this->status,
            'priority' => $this->when($includeOwnerFields, $this->priority),
            'budget_impressions' => $this->when($includeOwnerFields, $this->budget_impressions),
            'budget_clicks' => $this->when($includeOwnerFields, $this->budget_clicks),
            'impressions_count' => $this->when($includeOwnerFields, $this->impressions_count),
            'clicks_count' => $this->when($includeOwnerFields, $this->clicks_count),
            'ctr' => $this->when($includeOwnerFields, $this->clickThroughRate()),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'is_admin_campaign' => (bool) $this->is_admin_campaign,
            'is_sponsored' => true,
            'badge' => 'Sponsored',
            'metadata' => $this->when($includeOwnerFields, $this->metadata),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
