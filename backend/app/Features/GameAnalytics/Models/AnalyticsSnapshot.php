<?php

namespace App\Features\GameAnalytics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AnalyticsSnapshot extends Model
{
    use HasUuids;

    protected $table = 'game_analytics_snapshots';

    protected $fillable = [
        'snapshot_type', 'period_key', 'payload', 'captured_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'captured_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
