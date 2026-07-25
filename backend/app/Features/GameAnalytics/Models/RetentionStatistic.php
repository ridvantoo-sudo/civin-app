<?php

namespace App\Features\GameAnalytics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RetentionStatistic extends Model
{
    use HasUuids;

    protected $table = 'game_retention_statistics';

    protected $fillable = [
        'cohort_date', 'cohort_type', 'cohort_key', 'cohort_size',
        'retained_d1', 'retained_d7', 'retained_d30',
        'retention_d1_rate', 'retention_d7_rate', 'retention_d30_rate', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'cohort_date' => 'date',
            'retention_d1_rate' => 'decimal:4',
            'retention_d7_rate' => 'decimal:4',
            'retention_d30_rate' => 'decimal:4',
            'metadata' => 'array',
        ];
    }
}
