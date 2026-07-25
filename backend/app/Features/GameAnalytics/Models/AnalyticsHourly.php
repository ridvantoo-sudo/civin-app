<?php

namespace App\Features\GameAnalytics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AnalyticsHourly extends Model
{
    use HasUuids;

    protected $table = 'game_analytics_hourly';

    protected $fillable = [
        'hour_start', 'game_slug', 'country_code', 'device_platform',
        'games_played', 'unique_players', 'matches_started', 'matches_finished',
        'reconnect_count', 'abandoned_games', 'coins_wagered', 'coins_won',
        'coins_burned', 'diamonds_consumed', 'revenue', 'profit',
        'avg_duration_seconds', 'avg_bet', 'avg_reward', 'rtp', 'house_edge',
        'jackpots', 'bonus_wins', 'free_plays', 'peak_ccu', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'hour_start' => 'datetime',
            'coins_wagered' => 'decimal:4',
            'coins_won' => 'decimal:4',
            'coins_burned' => 'decimal:4',
            'diamonds_consumed' => 'decimal:4',
            'revenue' => 'decimal:4',
            'profit' => 'decimal:4',
            'avg_duration_seconds' => 'decimal:2',
            'avg_bet' => 'decimal:4',
            'avg_reward' => 'decimal:4',
            'rtp' => 'decimal:4',
            'house_edge' => 'decimal:4',
            'metadata' => 'array',
        ];
    }
}
