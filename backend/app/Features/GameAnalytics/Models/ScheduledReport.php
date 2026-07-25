<?php

namespace App\Features\GameAnalytics\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledReport extends Model
{
    use HasUuids;

    protected $table = 'game_analytics_scheduled_reports';

    protected $fillable = [
        'name', 'report_type', 'frequency', 'recipients', 'filters', 'formats',
        'is_active', 'next_run_at', 'last_run_at', 'last_status', 'last_error',
        'created_by', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'recipients' => 'array',
            'filters' => 'array',
            'formats' => 'array',
            'is_active' => 'boolean',
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
