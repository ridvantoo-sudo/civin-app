<?php

namespace App\Features\Games\Plugins\Quiz\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizPlayer extends Model
{
    use HasUuids;

    public const STATUS_JOINED = 'joined';

    public const STATUS_PLAYING = 'playing';

    public const STATUS_FINISHED = 'finished';

    public const STATUS_LEFT = 'left';

    protected $fillable = [
        'session_id', 'user_id', 'idempotency_key', 'status', 'team_id', 'team_name',
        'is_host', 'score', 'correct_count', 'wrong_count', 'streak', 'best_streak',
        'total_response_ms', 'answers_count', 'rank', 'is_winner', 'metadata',
        'joined_at', 'left_at',
    ];

    protected function casts(): array
    {
        return [
            'is_host' => 'boolean',
            'is_winner' => 'boolean',
            'metadata' => 'array',
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class, 'session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class, 'player_id');
    }
}
