<?php

namespace App\Features\Couple\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoupleTask extends Model
{
    use HasUuids;

    public const PERIOD_DAILY = 'daily';

    public const PERIOD_WEEKLY = 'weekly';

    protected $fillable = [
        'key',
        'title',
        'description',
        'period',
        'target_value',
        'reward_xp',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'target_value' => 'integer',
            'reward_xp' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function progressRows(): HasMany
    {
        return $this->hasMany(CoupleTaskProgress::class, 'task_id');
    }
}
