<?php

namespace App\Features\Marketplace\Models;

use Database\Factories\MarketItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketItem extends Model
{
    /** @use HasFactory<MarketItemFactory> */
    use HasFactory, HasUuids;

    public const TYPE_FRAME = 'frame';

    public const TYPE_ENTRY_EFFECT = 'entry_effect';

    public const TYPE_CHAT_BUBBLE = 'chat_bubble';

    public const TYPE_PROFILE_DECORATION = 'profile_decoration';

    public const TYPE_SPECIAL_USER_ID = 'special_user_id';

    public const TYPE_PROFILE_THEME = 'profile_theme';

    public const TYPE_STICKER_PACK = 'sticker_pack';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /** @return list<string> */
    public static function types(): array
    {
        return [
            self::TYPE_FRAME,
            self::TYPE_ENTRY_EFFECT,
            self::TYPE_CHAT_BUBBLE,
            self::TYPE_PROFILE_DECORATION,
            self::TYPE_SPECIAL_USER_ID,
            self::TYPE_PROFILE_THEME,
            self::TYPE_STICKER_PACK,
        ];
    }

    protected $fillable = [
        'type',
        'name',
        'description',
        'icon_url',
        'asset_url',
        'coin_price',
        'duration_days',
        'is_limited',
        'stock_total',
        'stock_remaining',
        'special_value',
        'membership_required',
        'status',
        'sort_order',
        'starts_at',
        'ends_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'coin_price' => 'integer',
            'duration_days' => 'integer',
            'is_limited' => 'boolean',
            'stock_total' => 'integer',
            'stock_remaining' => 'integer',
            'sort_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(MarketPurchase::class, 'market_item_id');
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(UserMarketItem::class, 'market_item_id');
    }

    public function stickers(): HasMany
    {
        return $this->hasMany(StickerPackItem::class, 'market_item_id')->orderBy('sort_order');
    }

    public function isPurchasableNow(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        $now = now();

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return false;
        }

        if ($this->is_limited && $this->stock_remaining !== null && $this->stock_remaining < 1) {
            return false;
        }

        return true;
    }

    public function isPermanent(): bool
    {
        return $this->duration_days === null || $this->duration_days < 1;
    }
}
