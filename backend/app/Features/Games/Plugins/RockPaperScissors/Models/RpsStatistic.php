<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RpsStatistic extends Model
{
    use HasUuids;

    protected $table = 'rps_statistics';

    protected $fillable = [
        'user_id', 'config_id',
        'matches_played', 'matches_won', 'matches_lost', 'matches_drawn', 'forfeits',
        'rounds_played', 'rounds_won', 'rounds_lost', 'rounds_drawn',
        'current_streak', 'best_streak', 'win_rate', 'average_response_ms',
        'coins_wagered', 'coins_won', 'diamonds_won',
        'tournaments_played', 'tournaments_won', 'move_distribution', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'win_rate' => 'float',
            'average_response_ms' => 'float',
            'move_distribution' => 'array',
            'metadata' => 'array',
        ];
    }
}
