<?php

namespace App\Features\Games\Plugins\Quiz\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizSession extends Model
{
    use HasUuids;

    public const STATUS_LOBBY = 'lobby';

    public const STATUS_STARTING = 'starting';

    public const STATUS_QUESTION = 'question';

    public const STATUS_BETWEEN = 'between';

    public const STATUS_SETTLING = 'settling';

    public const STATUS_FINISHED = 'finished';

    public const STATUS_CANCELLED = 'cancelled';

    public const MODE_CLASSIC = 'classic';

    public const MODE_SPEED = 'speed';

    public const MODE_LIVE_HOST = 'live_host';

    public const MODE_VOICE_ROOM = 'voice_room';

    public const MODE_TEAM = 'team';

    public const MODE_TOURNAMENT = 'tournament';

    public const MODE_DAILY = 'daily';

    protected $fillable = [
        'config_id', 'game_id', 'host_id', 'tournament_id', 'category_id',
        'idempotency_key', 'mode', 'difficulty', 'status', 'visibility', 'eligibility',
        'reward_type', 'reward_pool', 'bet_coins', 'question_count', 'time_per_question',
        'winner_count', 'current_round', 'current_question_index', 'current_question_id',
        'room_id', 'room_type', 'language', 'player_count', 'config_version',
        'question_ids', 'scoring_rules', 'whitelist_user_ids', 'team_settings',
        'audit_log', 'metadata', 'question_started_at', 'question_ends_at',
        'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'question_ids' => 'array',
            'scoring_rules' => 'array',
            'whitelist_user_ids' => 'array',
            'team_settings' => 'array',
            'audit_log' => 'array',
            'metadata' => 'array',
            'question_started_at' => 'datetime',
            'question_ends_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(QuizConfig::class, 'config_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(QuizCategory::class, 'category_id');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(QuizTournament::class, 'tournament_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(QuizPlayer::class, 'session_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class, 'session_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(QuizScore::class, 'session_id');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(QuizReward::class, 'session_id');
    }

    public function isJoinable(): bool
    {
        return in_array($this->status, [self::STATUS_LOBBY, self::STATUS_STARTING], true);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_LOBBY, self::STATUS_STARTING, self::STATUS_QUESTION, self::STATUS_BETWEEN,
        ], true);
    }
}
