<?php

namespace App\Features\Marketplace\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StickerPackItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'market_item_id',
        'code',
        'label',
        'asset_url',
        'preview_url',
        'sort_order',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(MarketItem::class, 'market_item_id');
    }
}
