<?php

namespace App\Features\GameAnalytics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CountryStatistic extends Model
{
    use HasUuids;

    protected $table = 'game_country_statistics';

    protected $fillable = [
        'stat_date', 'country_code', 'dau', 'games_played', 'revenue', 'coins_wagered', 'metadata',
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
