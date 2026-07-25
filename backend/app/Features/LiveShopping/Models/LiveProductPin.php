<?php

namespace App\Features\LiveShopping\Models;

use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveProductPin extends Model
{
    use HasUuids;

    protected $fillable = [
        'room_id',
        'product_id',
        'pinned_by',
        'is_pinned',
        'flash_deal_price',
        'flash_deal_ends_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'flash_deal_price' => 'integer',
            'flash_deal_ends_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(LiveRoom::class, 'room_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class, 'product_id');
    }

    public function pinner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pinned_by');
    }

    public function hasActiveFlashDeal(): bool
    {
        if ($this->flash_deal_price === null) {
            return false;
        }

        return $this->flash_deal_ends_at === null || $this->flash_deal_ends_at->isFuture();
    }

    public function effectivePrice(): int
    {
        if ($this->hasActiveFlashDeal()) {
            return (int) $this->flash_deal_price;
        }

        return (int) ($this->product?->price_coins ?? 0);
    }
}
