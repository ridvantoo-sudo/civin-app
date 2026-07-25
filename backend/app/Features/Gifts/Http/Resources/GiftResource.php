<?php

namespace App\Features\Gifts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class GiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $this->icon,
            'animation_url' => $this->animation_url,
            'coin_price' => $this->coin_price,
            'status' => $this->status,
            'membership_required' => $this->membership_required,
            'animation' => $this->animation()->toArray(),
            'category' => new GiftCategoryResource($this->whenLoaded('category')),
        ];
    }
}
