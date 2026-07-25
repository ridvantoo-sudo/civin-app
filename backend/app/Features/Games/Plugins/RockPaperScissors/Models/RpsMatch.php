<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RpsMatch extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_MATCHING = 'matching';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SETTLING = 'settling';

    public const STATUS_FINISHED = 'finished';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const RESULT_CHALLENGER_WON = 'challenger_won';

    public const RESULT_OPPONENT_WON = 'opponent_won';

    public const RESULT_DRAW = 'draw';

    public const RESULT_FORFEIT = 'forfeit';

    public const RESULT_EXPIRED = 'expired';

    public const RESULT_CANCELLED = 'cancelled';

    public const MODE_CLASSIC_1V1 = 'classic_1v1';

    public const MODE_BEST_OF_3 = 'best_of_3';

    public const MODE_BEST_OF_5 = 'best_of_5';

    public const MODE_LIVE_ROOM = 'live_room';

    public const MODE_VOICE_ROOM = 'voice_room';

    public const MODE_TOURNAMENT = 'tournament';

    public const MODE_VIP_ARENA = 'vip_arena';

    public const MATCHMAKING_PRIVATE = 'private';

    public const MATCHMAKING_FRIEND = 'friend';

    public const MATCHMAKING_RANDOM = 'random';

    public const MATCHMAKING_LIVE = 'live';

    public const MATCHMAKING_VOICE = 'voice';

    public const MATCHMAKING_TOURNAMENT = 'tournament';

    public const MATCHMAKING_REMATCH = 'rematch';

    public const MATCHMAKING_QUICK_PLAY = 'quick_play';

    public const ROLE_CHALLENGER = 'challenger';

    public const ROLE_OPPONENT = 'opponent';

    /** Matchmaking types that are auto-paired with any waiting opponent. */
    public const OPEN_MATCHMAKING = [self::MATCHMAKING_RANDOM, self::MATCHMAKING_QUICK_PLAY];

    protected $table = 'rps_matches';

    protected $fillable = [
        'config_id', 'game_id', 'tournament_id', 'bracket_id', 'parent_match_id',
        'challenger_id', 'opponent_id', 'winner_id', 'loser_id', 'forfeited_by',
        'idempotency_key', 'opponent_idempotency_key',
        'mode', 'matchmaking', 'status', 'result', 'visibility', 'eligibility',
        'reward_type', 'entry_fee_coins', 'pot_coins', 'rake_coins',
        'rounds_target', 'wins_required', 'max_rounds', 'rounds_played',
        'challenger_score', 'opponent_score', 'draw_count',
        'challenger_missed', 'opponent_missed',
        'current_round', 'current_round_id', 'round_timeout_seconds',
        'room_id', 'room_type', 'is_vip_arena', 'is_rematch', 'rematch_count',
        'config_version', 'move_rules', 'whitelist_user_ids', 'audit_log', 'tamper_flags', 'metadata',
        'accept_deadline_at', 'started_at', 'finished_at', 'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'move_rules' => 'array',
            'whitelist_user_ids' => 'array',
            'audit_log' => 'array',
            'tamper_flags' => 'array',
            'metadata' => 'array',
            'is_vip_arena' => 'boolean',
            'is_rematch' => 'boolean',
            'accept_deadline_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public function challenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'challenger_id');
    }

    public function opponent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opponent_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(RpsConfig::class, 'config_id');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(RpsTournament::class, 'tournament_id');
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(RpsRound::class, 'match_id');
    }

    public function moves(): HasMany
    {
        return $this->hasMany(RpsMove::class, 'match_id');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(RpsReward::class, 'match_id');
    }

    public function isJoinable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_MATCHING], true);
    }

    public function isPlayable(): bool
    {
        return in_array($this->status, [self::STATUS_ACCEPTED, self::STATUS_ACTIVE], true);
    }

    public function isOver(): bool
    {
        return in_array($this->status, [
            self::STATUS_FINISHED, self::STATUS_EXPIRED, self::STATUS_CANCELLED,
        ], true);
    }

    public function roleFor(string $userId): ?string
    {
        if ((string) $this->challenger_id === $userId) {
            return self::ROLE_CHALLENGER;
        }

        if ($this->opponent_id !== null && (string) $this->opponent_id === $userId) {
            return self::ROLE_OPPONENT;
        }

        return null;
    }

    public function opponentIdFor(string $userId): ?string
    {
        return match ($this->roleFor($userId)) {
            self::ROLE_CHALLENGER => $this->opponent_id === null ? null : (string) $this->opponent_id,
            self::ROLE_OPPONENT => (string) $this->challenger_id,
            default => null,
        };
    }

    /** @return list<string> */
    public function participantIds(): array
    {
        return array_values(array_filter([
            (string) $this->challenger_id,
            $this->opponent_id === null ? null : (string) $this->opponent_id,
        ]));
    }

    /** @return array{moves: list<string>, beats: array<string, list<string>>} */
    public function moveRules(): array
    {
        $rules = $this->move_rules;

        return is_array($rules) && $rules !== [] ? $rules : RpsConfig::DEFAULT_MOVE_RULES;
    }
}
