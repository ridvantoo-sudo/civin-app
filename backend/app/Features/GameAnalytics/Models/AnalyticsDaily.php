<?php

namespace App\Features\GameAnalytics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AnalyticsDaily extends Model
{
    use HasUuids;

    protected $table = 'game_analytics_daily';

    protected $fillable = [
        'stat_date', 'game_slug', 'country_code', 'device_platform',
        'games_played', 'unique_players', 'returning_players', 'matches_started',
        'matches_finished', 'reconnect_count', 'abandoned_games', 'wins', 'losses',
        'coins_wagered', 'coins_won', 'coins_burned', 'diamonds_consumed', 'revenue',
        'profit', 'avg_duration_seconds', 'avg_session_length', 'avg_bet', 'avg_reward',
        'rtp', 'house_edge', 'win_rate', 'loss_rate', 'jackpots', 'bonus_wins',
        'free_plays', 'dau', 'peak_ccu', 'arpu', 'arppu', 'conversion_rate', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'coins_wagered' => 'decimal:4',
            'coins_won' => 'decimal:4',
            'coins_burned' => 'decimal:4',
            'diamonds_consumed' => 'decimal:4',
            'revenue' => 'decimal:4',
            'profit' => 'decimal:4',
            'avg_duration_seconds' => 'decimal:2',
            'avg_session_length' => 'decimal:2',
            'avg_bet' => 'decimal:4',
            'avg_reward' => 'decimal:4',
            'rtp' => 'decimal:4',
            'house_edge' => 'decimal:4',
            'win_rate' => 'decimal:4',
            'loss_rate' => 'decimal:4',
            'arpu' => 'decimal:4',
            'arppu' => 'decimal:4',
            'conversion_rate' => 'decimal:4',
            'metadata' => 'array',
        ];
    }
}
