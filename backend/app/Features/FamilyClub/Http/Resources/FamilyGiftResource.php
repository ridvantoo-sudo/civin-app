<?php

namespace App\Features\FamilyClub\Http\Resources;

use App\Features\FamilyClub\Models\FamilyGift;
use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FamilyGift */
final class FamilyGiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sender = $this->relationLoaded('sender') ? $this->sender : null;
        $gift = $this->relationLoaded('gift') ? $this->gift : null;

        return [
            'id' => $this->id,
            'family_id' => $this->family_id,
            'quantity' => $this->quantity,
            'coins' => $this->coins,
            'xp_awarded' => $this->xp_awarded,
            'metadata' => $this->metadata,
            'gift' => $gift === null ? null : [
                'id' => $gift->id,
                'name' => $gift->name,
                'icon' => $gift->icon,
                'coin_price' => $gift->coin_price,
            ],
            'sender' => $sender === null ? null : new SocialUserResource($sender),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
