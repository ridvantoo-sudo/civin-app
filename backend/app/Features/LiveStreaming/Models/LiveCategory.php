<?php

namespace App\Features\LiveStreaming\Models;

use Database\Factories\LiveCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveCategory extends Model
{
    /** @use HasFactory<LiveCategoryFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['name', 'icon', 'status', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(LiveRoom::class, 'category_id');
    }
}
