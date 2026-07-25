<?php

namespace App\Features\LiveShopping\Models;

use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShoppingCart extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'room_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(LiveRoom::class, 'room_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShoppingCartItem::class, 'cart_id');
    }

    public function totalCoins(): int
    {
        return (int) $this->items->sum(fn (ShoppingCartItem $item): int => $item->lineTotal());
    }

    public function itemCount(): int
    {
        return (int) $this->items->sum('quantity');
    }
}
