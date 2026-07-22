<?php

namespace App\Features\Gifts\Http\Resources;

use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class GiftTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $gift = $this->whenLoaded('gift');

        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'quantity' => $this->quantity,
            'coins' => $this->coins,
            'metadata' => $this->metadata,
            'sender' => new SocialUserResource($this->whenLoaded('sender')),
            'receiver' => new SocialUserResource($this->whenLoaded('receiver')),
            'gift' => $gift ? new GiftResource($gift) : null,
            'animation' => $gift ? $gift->animation()->toArray() : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
