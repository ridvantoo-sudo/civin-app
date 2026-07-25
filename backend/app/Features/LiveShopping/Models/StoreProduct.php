<?php

namespace App\Features\LiveShopping\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreProduct extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'seller_id',
        'name',
        'description',
        'image_url',
        'price_coins',
        'stock',
        'status',
        'is_offline_available',
        'sort_order',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'price_coins' => 'integer',
            'stock' => 'integer',
            'is_offline_available' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function pins(): HasMany
    {
        return $this->hasMany(LiveProductPin::class, 'product_id');
    }

    public function isPurchasable(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->stock > 0;
    }
}
