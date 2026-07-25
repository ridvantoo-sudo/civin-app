<?php

namespace App\Features\Couple\Http\Resources;

use App\Features\Couple\Models\CoupleProposal;
use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CoupleProposal */
final class CoupleProposalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $from = $this->relationLoaded('fromUser') ? $this->fromUser : null;
        $to = $this->relationLoaded('toUser') ? $this->toUser : null;

        return [
            'id' => $this->id,
            'message' => $this->message,
            'status' => $this->status,
            'couple_id' => $this->couple_id,
            'expires_at' => $this->expires_at?->toISOString(),
            'responded_at' => $this->responded_at?->toISOString(),
            'from_user' => $from === null ? null : new SocialUserResource($from),
            'to_user' => $to === null ? null : new SocialUserResource($to),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
