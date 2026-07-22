<?php

namespace App\Features\Gifts\Models;

use Database\Factories\GiftFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gift extends Model
{
    /** @use HasFactory<GiftFactory> */
    use HasFactory, HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public $timestamps = false;

    protected $fillable = [
        'category_id', 'name', 'icon', 'animation_url', 'coin_price', 'status',
    ];

    protected function casts(): array
    {
        return ['coin_price' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(GiftCategory::class, 'category_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(GiftTransaction::class, 'gift_id');
    }

    public function animation(): GiftAnimation
    {
        return GiftAnimation::fromGift($this);
    }
}
