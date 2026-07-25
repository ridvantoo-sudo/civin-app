<?php

namespace App\Features\Games\Plugins\Quiz\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizCategory extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const DEFAULT_SLUGS = [
        'technology', 'sports', 'movies', 'music', 'history',
        'science', 'geography', 'gaming', 'general_knowledge',
    ];

    protected $fillable = [
        'slug', 'name', 'description', 'icon', 'is_custom', 'status', 'sort_order', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_custom' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class, 'category_id');
    }
}
