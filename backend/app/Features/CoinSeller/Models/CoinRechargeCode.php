<?php

namespace App\Features\CoinSeller\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CoinRechargeCode extends Model
{
    use HasUuids;

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_REDEEMED = 'redeemed';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'coin_seller_id',
        'code',
        'coins',
        'status',
        'redeemed_by',
        'redeemed_at',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'coins' => 'integer',
            'redeemed_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(CoinSeller::class, 'coin_seller_id');
    }

    public function redeemer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by');
    }

    public function sale(): HasOne
    {
        return $this->hasOne(CoinSellerSale::class, 'recharge_code_id');
    }

    public function isAvailable(): bool
    {
        if ($this->status !== self::STATUS_AVAILABLE) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
