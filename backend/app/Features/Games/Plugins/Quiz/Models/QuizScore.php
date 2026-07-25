<?php

namespace App\Features\Games\Plugins\Quiz\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizScore extends Model
{
    use HasUuids;

    protected $fillable = [
        'session_id', 'player_id', 'user_id', 'team_id', 'total_score',
        'correct_count', 'wrong_count', 'speed_bonus_total', 'streak_bonus_total',
        'perfect_round_bonus', 'tournament_bonus', 'vip_bonus', 'rank',
        'is_winner', 'breakdown',
    ];

    protected function casts(): array
    {
        return [
            'is_winner' => 'boolean',
            'breakdown' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class, 'session_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(QuizPlayer::class, 'player_id');
    }
}
