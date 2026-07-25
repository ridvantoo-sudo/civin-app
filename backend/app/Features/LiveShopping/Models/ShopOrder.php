<?php

namespace App\Features\LiveShopping\Models;

use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopOrder extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_PENDING = 'pending';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_FAILED = 'failed';

    public const PAYMENT_REFUNDED = 'refunded';

    public const METHOD_WALLET = 'wallet';

    public const METHOD_EXTERNAL = 'external';

    public const WALLET_TYPE_LIVE_SHOPPING = 'LIVE_SHOPPING_PURCHASE';

    /** @return list<string> */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
            self::STATUS_SHIPPED,
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED,
        ];
    }

    /** @return list<string> */
    public static function shippingStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
            self::STATUS_SHIPPED,
            self::STATUS_DELIVERED,
        ];
    }

    protected $fillable = [
        'order_number',
        'buyer_id',
        'seller_id',
        'room_id',
        'status',
        'payment_status',
        'payment_method',
        'payment_provider',
        'payment_transaction_id',
        'total_coins',
        'subtotal_coins',
        'discount_coins',
        'store_coupon_id',
        'coupon_code',
        'shipping_name',
        'shipping_phone',
        'shipping_address_line',
        'shipping_city',
        'shipping_country',
        'shipping_postal_code',
        'tracking_number',
        'paid_at',
        'shipped_at',
        'delivered_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'total_coins' => 'integer',
            'subtotal_coins' => 'integer',
            'discount_coins' => 'integer',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(StoreCoupon::class, 'store_coupon_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(LiveRoom::class, 'room_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShopOrderItem::class, 'order_id');
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_PAID;
    }
}
