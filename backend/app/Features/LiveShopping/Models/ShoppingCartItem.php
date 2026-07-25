<?php

namespace App\Features\LiveShopping\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShoppingCartItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'cart_id',
        'product_id',
        'live_pin_id',
        'quantity',
        'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(ShoppingCart::class, 'cart_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class, 'product_id');
    }

    public function livePin(): BelongsTo
    {
        return $this->belongsTo(LiveProductPin::class, 'live_pin_id');
    }

    public function lineTotal(): int
    {
        return $this->quantity * $this->unit_price;
    }
}
