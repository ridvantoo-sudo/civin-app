<?php

namespace App\Features\GameAnalytics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AnalyticsMonthly extends Model
{
    use HasUuids;

    protected $table = 'game_analytics_monthly';

    protected $fillable = [
        'period_key', 'game_slug', 'country_code', 'device_platform',
        'games_played', 'unique_players', 'returning_players', 'matches_started',
        'matches_finished', 'coins_wagered', 'coins_won', 'coins_burned',
        'diamonds_consumed', 'revenue', 'profit', 'rtp', 'house_edge',
        'mau', 'wau', 'arpu', 'arppu', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'coins_wagered' => 'decimal:4',
            'coins_won' => 'decimal:4',
            'coins_burned' => 'decimal:4',
            'diamonds_consumed' => 'decimal:4',
            'revenue' => 'decimal:4',
            'profit' => 'decimal:4',
            'rtp' => 'decimal:4',
            'house_edge' => 'decimal:4',
            'arpu' => 'decimal:4',
            'arppu' => 'decimal:4',
            'metadata' => 'array',
        ];
    }
}
