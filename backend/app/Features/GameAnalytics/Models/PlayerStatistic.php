<?php

namespace App\Features\GameAnalytics\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerStatistic extends Model
{
    use HasUuids;

    protected $table = 'game_player_statistics';

    protected $fillable = [
        'user_id', 'game_slug', 'stat_date', 'games_played', 'wins', 'losses',
        'reconnect_count', 'coins_wagered', 'coins_won', 'revenue_contributed',
        'avg_session_length', 'first_played_at', 'last_played_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'coins_wagered' => 'decimal:4',
            'coins_won' => 'decimal:4',
            'revenue_contributed' => 'decimal:4',
            'avg_session_length' => 'decimal:2',
            'first_played_at' => 'datetime',
            'last_played_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
