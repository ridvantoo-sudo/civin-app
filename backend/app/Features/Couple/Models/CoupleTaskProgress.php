<?php

namespace App\Features\Couple\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoupleTaskProgress extends Model
{
    use HasUuids;

    protected $table = 'couple_task_progress';

    protected $fillable = [
        'couple_id',
        'task_id',
        'period_key',
        'progress',
        'completed_at',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'progress' => 'integer',
            'completed_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(CoupleTask::class, 'task_id');
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
