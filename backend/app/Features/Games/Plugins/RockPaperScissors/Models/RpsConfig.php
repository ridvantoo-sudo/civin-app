<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RpsConfig extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const MODES = [
        'classic_1v1',
        'best_of_3',
        'best_of_5',
        'live_room',
        'voice_room',
        'tournament',
        'vip_arena',
    ];

    public const MATCHMAKING = [
        'private',
        'friend',
        'random',
        'live',
        'voice',
        'tournament',
        'rematch',
        'quick_play',
    ];

    public const REWARD_TYPES = [
        'coins',
        'diamonds',
        'gift',
        'vip_days',
        'svip_days',
        'frame',
        'entry_effect',
        'mystery',
        'tournament_trophy',
    ];

    public const VISIBILITIES = ['public', 'private', 'friends', 'room'];

    public const ELIGIBILITIES = ['everyone', 'vip', 'svip', 'followers', 'whitelist', 'agency', 'family'];

    public const TOURNAMENT_FORMATS = ['single_elimination', 'double_elimination', 'round_robin'];

    /** Wallet-settling reward types. Everything else is fulfilled out of band. */
    public const WALLET_REWARD_TYPES = ['coins', 'diamonds'];

    /**
     * Default move rule set. `beats` maps a move to every move it defeats, so
     * extended variants (e.g. lizard/spock) only need a config change.
     */
    public const DEFAULT_MOVE_RULES = [
        'moves' => ['rock', 'paper', 'scissors'],
        'beats' => [
            'rock' => ['scissors'],
            'paper' => ['rock'],
            'scissors' => ['paper'],
        ],
    ];

    /** Rounds a player must win, per mode. */
    public const DEFAULT_MODE_ROUNDS = [
        'classic_1v1' => 1,
        'best_of_3' => 3,
        'best_of_5' => 5,
        'live_room' => 3,
        'voice_room' => 3,
        'tournament' => 3,
        'vip_arena' => 5,
    ];

    protected $fillable = [
        'game_id', 'slug', 'name', 'description', 'version',
        'min_entry_fee_coins', 'max_entry_fee_coins', 'default_entry_fee_coins',
        'default_round_timeout_seconds', 'min_round_timeout_seconds', 'max_round_timeout_seconds',
        'accept_timeout_seconds', 'match_timeout_seconds', 'max_rounds',
        'forfeit_after_missed_rounds', 'rematch_limit', 'max_created_per_day', 'cooldown_seconds',
        'house_rake_bps', 'vip_reward_multiplier',
        'modes_enabled', 'matchmaking_enabled', 'reward_types_enabled', 'visibilities_enabled',
        'eligibilities_enabled', 'tournament_formats_enabled', 'move_rules', 'mode_rounds',
        'enabled', 'status', 'is_default', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'modes_enabled' => 'array',
            'matchmaking_enabled' => 'array',
            'reward_types_enabled' => 'array',
            'visibilities_enabled' => 'array',
            'eligibilities_enabled' => 'array',
            'tournament_formats_enabled' => 'array',
            'move_rules' => 'array',
            'mode_rounds' => 'array',
            'metadata' => 'array',
            'enabled' => 'boolean',
            'is_default' => 'boolean',
            'vip_reward_multiplier' => 'float',
        ];
    }

    public function matches(): HasMany
    {
        return $this->hasMany(RpsMatch::class, 'config_id');
    }

    public function isActive(): bool
    {
        return $this->enabled && $this->status === self::STATUS_ACTIVE;
    }

    /** @return array{moves: list<string>, beats: array<string, list<string>>} */
    public function moveRules(): array
    {
        $rules = $this->move_rules;

        return is_array($rules) && $rules !== [] ? $rules : self::DEFAULT_MOVE_RULES;
    }

    /** Wins required to take the match in the given mode. */
    public function winsRequiredFor(string $mode): int
    {
        $map = is_array($this->mode_rounds) && $this->mode_rounds !== []
            ? $this->mode_rounds
            : self::DEFAULT_MODE_ROUNDS;

        $best = (int) ($map[$mode] ?? 1);

        return (int) ceil(max(1, $best) / 2);
    }

    /** Best-of length for the given mode. */
    public function roundsTargetFor(string $mode): int
    {
        $map = is_array($this->mode_rounds) && $this->mode_rounds !== []
            ? $this->mode_rounds
            : self::DEFAULT_MODE_ROUNDS;

        return max(1, (int) ($map[$mode] ?? 1));
    }
}
