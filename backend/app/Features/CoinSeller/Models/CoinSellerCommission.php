<?php

namespace App\Features\CoinSeller\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoinSellerCommission extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'coin_seller_id',
        'sale_id',
        'gross_coins',
        'commission_rate',
        'commission_amount',
        'seller_net_coins',
        'currency',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_coins' => 'integer',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'integer',
            'seller_net_coins' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(CoinSeller::class, 'coin_seller_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(CoinSellerSale::class, 'sale_id');
    }
}
