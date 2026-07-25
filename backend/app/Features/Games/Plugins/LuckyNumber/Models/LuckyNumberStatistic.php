<?php

namespace App\Features\Games\Plugins\LuckyNumber\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LuckyNumberStatistic extends Model
{
    use HasUuids;

    protected $table = 'lucky_number_statistics';

    protected $fillable = [
        'user_id',
        'config_id',
        'games_played',
        'tickets_purchased',
        'coins_wagered',
        'coins_won',
        'diamonds_won',
        'jackpots_won',
        'highest_win',
        'average_win',
        'win_percentage',
        'current_streak',
        'best_streak',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'games_played' => 'integer',
            'tickets_purchased' => 'integer',
            'coins_wagered' => 'integer',
            'coins_won' => 'integer',
            'diamonds_won' => 'integer',
            'jackpots_won' => 'integer',
            'highest_win' => 'integer',
            'average_win' => 'float',
            'win_percentage' => 'float',
            'current_streak' => 'integer',
            'best_streak' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(LuckyNumberConfig::class, 'config_id');
    }
}
