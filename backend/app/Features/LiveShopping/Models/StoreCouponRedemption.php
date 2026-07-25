<?php

namespace App\Features\LiveShopping\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreCouponRedemption extends Model
{
    use HasUuids;

    protected $fillable = [
        'store_coupon_id',
        'user_id',
        'shop_order_id',
        'discount_coins',
    ];

    protected function casts(): array
    {
        return [
            'discount_coins' => 'integer',
        ];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(StoreCoupon::class, 'store_coupon_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ShopOrder::class, 'shop_order_id');
    }
}
