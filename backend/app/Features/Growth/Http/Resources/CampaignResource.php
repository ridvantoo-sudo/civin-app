<?php

namespace App\Features\Growth\Http\Resources;

use App\Features\Growth\Models\GrowthCampaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GrowthCampaign */
final class CampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'channel' => $this->channel,
            'status' => $this->status,
            'subject' => $this->subject,
            'body' => $this->body,
            'action_url' => $this->action_url,
            'audience' => $this->audience,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'targeted_count' => $this->targeted_count,
            'sent_count' => $this->sent_count,
            'failed_count' => $this->failed_count,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
