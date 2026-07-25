<?php

namespace App\Features\Levels\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDailyTaskProgress extends Model
{
    use HasUuids;

    protected $table = 'user_daily_task_progress';

    protected $fillable = [
        'user_id',
        'daily_task_id',
        'task_date',
        'progress',
        'completed_at',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'task_date' => 'date',
            'progress' => 'integer',
            'completed_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(DailyTask::class, 'daily_task_id');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isClaimed(): bool
    {
        return $this->claimed_at !== null;
    }
}
