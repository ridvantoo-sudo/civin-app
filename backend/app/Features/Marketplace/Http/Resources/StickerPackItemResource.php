<?php

namespace App\Features\Marketplace\Http\Resources;

use App\Features\Marketplace\Models\StickerPackItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StickerPackItem */
final class StickerPackItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'market_item_id' => $this->market_item_id,
            'code' => $this->code,
            'label' => $this->label,
            'asset_url' => $this->asset_url,
            'preview_url' => $this->preview_url,
            'sort_order' => $this->sort_order,
            'metadata' => $this->metadata,
        ];
    }
}
