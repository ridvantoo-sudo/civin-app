<?php

namespace App\Features\CoinSeller\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CoinSellerSale extends Model
{
    use HasUuids;

    public const TYPE_DIRECT_TRANSFER = 'direct_transfer';

    public const TYPE_RECHARGE_CODE = 'recharge_code';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'coin_seller_id',
        'buyer_id',
        'recharge_code_id',
        'type',
        'coins',
        'price_amount',
        'currency',
        'commission_rate',
        'commission_amount',
        'seller_net_coins',
        'status',
        'metadata',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'coins' => 'integer',
            'price_amount' => 'integer',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'integer',
            'seller_net_coins' => 'integer',
            'metadata' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(CoinSeller::class, 'coin_seller_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function rechargeCode(): BelongsTo
    {
        return $this->belongsTo(CoinRechargeCode::class, 'recharge_code_id');
    }

    public function commission(): HasOne
    {
        return $this->hasOne(CoinSellerCommission::class, 'sale_id');
    }
}
