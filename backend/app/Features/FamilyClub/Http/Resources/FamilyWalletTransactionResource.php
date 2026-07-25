<?php

namespace App\Features\FamilyClub\Http\Resources;

use App\Features\FamilyClub\Models\FamilyWalletTransaction;
use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FamilyWalletTransaction */
final class FamilyWalletTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->relationLoaded('user') ? $this->user : null;

        return [
            'id' => $this->id,
            'family_id' => $this->family_id,
            'type' => $this->type,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'note' => $this->note,
            'metadata' => $this->metadata,
            'user' => $user === null ? null : new SocialUserResource($user),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
