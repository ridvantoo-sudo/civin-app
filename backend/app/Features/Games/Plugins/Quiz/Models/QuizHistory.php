<?php

namespace App\Features\Games\Plugins\Quiz\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizHistory extends Model
{
    use HasUuids;

    protected $table = 'quiz_histories';

    protected $fillable = [
        'user_id', 'session_id', 'config_id', 'game_id', 'play_id',
        'mode', 'result', 'score', 'rank', 'bet_coins', 'reward_coins',
        'reward_diamonds', 'is_winner', 'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'is_winner' => 'boolean',
            'snapshot' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class, 'session_id');
    }
}
