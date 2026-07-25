<?php

namespace App\Features\GameAnalytics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DeviceStatistic extends Model
{
    use HasUuids;

    protected $table = 'game_device_statistics';

    protected $fillable = [
        'stat_date', 'device_platform', 'dau', 'games_played', 'revenue', 'coins_wagered', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'revenue' => 'decimal:4',
            'coins_wagered' => 'decimal:4',
            'metadata' => 'array',
        ];
    }
}
