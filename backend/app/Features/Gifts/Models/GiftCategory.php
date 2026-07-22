<?php

namespace App\Features\Gifts\Models;

use Database\Factories\GiftCategoryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftCategory extends Model
{
    /** @use HasFactory<GiftCategoryFactory> */
    use HasFactory, HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public $timestamps = false;

    protected $fillable = ['name', 'icon', 'sort_order', 'status'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function gifts(): HasMany
    {
        return $this->hasMany(Gift::class, 'category_id');
    }
}
