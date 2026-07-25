<?php

namespace App\Features\LiveShopping\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreCoupon extends Model
{
    use HasUuids;

    protected $fillable = [
        'seller_id',
        'code',
        'type',
        'value',
        'min_order_coins',
        'max_discount_coins',
        'max_redemptions',
        'redemptions_count',
        'per_user_limit',
        'active',
        'starts_at',
        'ends_at',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'min_order_coins' => 'integer',
            'max_discount_coins' => 'integer',
            'max_redemptions' => 'integer',
            'redemptions_count' => 'integer',
            'per_user_limit' => 'integer',
            'active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(StoreCouponRedemption::class, 'store_coupon_id');
    }

    public function isCurrentlyValid(): bool
    {
        if (! $this->active) {
            return false;
        }

        $now = now();
        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return false;
        }
        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return false;
        }
        if ($this->max_redemptions !== null && $this->redemptions_count >= $this->max_redemptions) {
            return false;
        }

        return true;
    }
}
