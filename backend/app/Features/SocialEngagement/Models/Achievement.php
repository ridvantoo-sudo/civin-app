<?php

namespace App\Features\SocialEngagement\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Achievement extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'name',
        'description',
        'icon',
        'category',
        'metric',
        'target',
        'coins_reward',
        'diamonds_reward',
        'xp_reward',
        'active',
        'sort_order',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'target' => 'integer',
            'coins_reward' => 'integer',
            'diamonds_reward' => 'integer',
            'xp_reward' => 'integer',
            'active' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function userProgress(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }
}
