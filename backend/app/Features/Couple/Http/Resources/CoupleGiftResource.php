<?php

namespace App\Features\Couple\Http\Resources;

use App\Features\Couple\Models\CoupleGift;
use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CoupleGift */
final class CoupleGiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sender = $this->relationLoaded('sender') ? $this->sender : null;
        $receiver = $this->relationLoaded('receiver') ? $this->receiver : null;
        $gift = $this->relationLoaded('gift') ? $this->gift : null;

        return [
            'id' => $this->id,
            'couple_id' => $this->couple_id,
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
            'receiver' => $receiver === null ? null : new SocialUserResource($receiver),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
