<?php

namespace App\Features\Levels\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyTask extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'code',
        'title',
        'description',
        'source',
        'target',
        'xp_reward',
        'icon',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'target' => 'integer',
            'xp_reward' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function progressRows(): HasMany
    {
        return $this->hasMany(UserDailyTaskProgress::class, 'daily_task_id');
    }
}
