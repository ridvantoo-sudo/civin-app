<?php

namespace App\Features\Games\Plugins\Quiz\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAnswer extends Model
{
    use HasUuids;

    protected $fillable = [
        'session_id', 'player_id', 'user_id', 'question_id', 'idempotency_key',
        'round_index', 'question_index', 'selected_index', 'selected_text',
        'is_correct', 'points_awarded', 'speed_bonus', 'streak_bonus',
        'response_ms', 'timed_out', 'server_seed', 'audit', 'metadata', 'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'timed_out' => 'boolean',
            'audit' => 'array',
            'metadata' => 'array',
            'answered_at' => 'datetime',
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

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'question_id');
    }
}
