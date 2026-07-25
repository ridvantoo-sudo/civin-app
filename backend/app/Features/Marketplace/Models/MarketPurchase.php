<?php

namespace App\Features\Marketplace\Models;

use App\Features\Users\Models\User;
use Database\Factories\MarketPurchaseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MarketPurchase extends Model
{
    /** @use HasFactory<MarketPurchaseFactory> */
    use HasFactory, HasUuids;

    public const WALLET_TYPE_MARKETPLACE_PURCHASE = 'MARKETPLACE_PURCHASE';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'market_item_id',
        'coins',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'coins' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
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

    public function inventoryItem(): HasOne
    {
        return $this->hasOne(UserMarketItem::class, 'purchase_id');
    }
}
