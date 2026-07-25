<?php

namespace App\Features\Marketplace\Models;

use App\Features\Users\Models\User;
use Database\Factories\UserMarketItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMarketItem extends Model
{
    /** @use HasFactory<UserMarketItemFactory> */
    use HasFactory, HasUuids;

    public const STATUS_OWNED = 'owned';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id',
        'market_item_id',
        'purchase_id',
        'status',
        'is_equipped',
        'acquired_at',
        'expires_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_equipped' => 'boolean',
            'acquired_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MarketItem::class, 'market_item_id');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(MarketPurchase::class, 'purchase_id');
    }

    public function isOwned(): bool
    {
        return $this->status === self::STATUS_OWNED;
    }

    public function isExpired(): bool
    {
        if ($this->status === self::STATUS_EXPIRED) {
            return true;
        }

        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->isOwned() && ! $this->isExpired();
    }
}
