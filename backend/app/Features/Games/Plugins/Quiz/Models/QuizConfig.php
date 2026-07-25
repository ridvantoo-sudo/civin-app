<?php

namespace App\Features\Games\Plugins\Quiz\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizConfig extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const MODES = [
        'classic',
        'speed',
        'live_host',
        'voice_room',
        'team',
        'tournament',
        'daily',
    ];

    public const QUESTION_TYPES = [
        'multiple_choice',
        'true_false',
        'image',
        'audio',
        'video',
        'text',
        'emoji',
        'puzzle',
    ];

    public const DIFFICULTIES = ['easy', 'medium', 'hard', 'expert', 'custom'];

    public const REWARD_TYPES = [
        'coins',
        'diamonds',
        'gift',
        'vip_days',
        'svip_days',
        'frame',
        'entry_effect',
        'mystery',
        'jackpot',
    ];

    public const VISIBILITIES = ['public', 'private', 'friends', 'room'];

    public const ELIGIBILITIES = ['everyone', 'vip', 'svip', 'followers', 'whitelist', 'agency', 'family'];

    public const DEFAULT_SCORING = [
        'correct_points' => 100,
        'speed_bonus_max' => 50,
        'streak_bonus_per' => 10,
        'perfect_round_bonus' => 200,
        'tournament_bonus' => 150,
        'vip_bonus_percent' => 10,
    ];

    protected $fillable = [
        'game_id', 'slug', 'name', 'description', 'version',
        'min_bet_coins', 'max_bet_coins', 'default_bet_coins',
        'default_question_count', 'max_question_count',
        'default_time_per_question', 'min_time_per_question', 'max_time_per_question',
        'default_winner_count', 'max_winner_count', 'max_players',
        'max_created_per_day', 'cooldown_seconds',
        'modes_enabled', 'question_types_enabled', 'difficulties_enabled',
        'reward_types_enabled', 'visibilities_enabled', 'eligibilities_enabled',
        'scoring_rules', 'vip_reward_multiplier', 'enabled', 'status', 'is_default', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'modes_enabled' => 'array',
            'question_types_enabled' => 'array',
            'difficulties_enabled' => 'array',
            'reward_types_enabled' => 'array',
            'visibilities_enabled' => 'array',
            'eligibilities_enabled' => 'array',
            'scoring_rules' => 'array',
            'metadata' => 'array',
            'enabled' => 'boolean',
            'is_default' => 'boolean',
            'vip_reward_multiplier' => 'float',
        ];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(QuizSession::class, 'config_id');
    }

    public function isActive(): bool
    {
        return $this->enabled && $this->status === self::STATUS_ACTIVE;
    }
}
