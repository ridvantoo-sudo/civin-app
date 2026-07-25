<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RpsTournament extends Model
{
    use HasUuids;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_REGISTRATION = 'registration';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FINISHED = 'finished';

    public const STATUS_CANCELLED = 'cancelled';

    public const FORMAT_SINGLE_ELIMINATION = 'single_elimination';

    public const FORMAT_DOUBLE_ELIMINATION = 'double_elimination';

    public const FORMAT_ROUND_ROBIN = 'round_robin';

    protected $table = 'rps_tournaments';

    protected $fillable = [
        'config_id', 'game_id', 'slug', 'name', 'description', 'format', 'status', 'mode',
        'rounds', 'current_round', 'max_players', 'min_players', 'registered_count',
        'entry_fee_coins', 'reward_type', 'reward_pool', 'prize_structure',
        'is_vip', 'is_daily', 'is_scheduled', 'eligibility', 'participant_ids',
        'bracket_seed', 'brackets_generated', 'winner_id', 'metadata',
        'registration_opens_at', 'registration_closes_at', 'scheduled_at', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'prize_structure' => 'array',
            'eligibility' => 'array',
            'participant_ids' => 'array',
            'metadata' => 'array',
            'is_vip' => 'boolean',
            'is_daily' => 'boolean',
            'is_scheduled' => 'boolean',
            'brackets_generated' => 'boolean',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(RpsConfig::class, 'config_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function brackets(): HasMany
    {
        return $this->hasMany(RpsBracket::class, 'tournament_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(RpsMatch::class, 'tournament_id');
    }

    public function isJoinable(): bool
    {
        return in_array($this->status, [self::STATUS_SCHEDULED, self::STATUS_REGISTRATION], true);
    }

    /** @return list<string> */
    public function participants(): array
    {
        $ids = $this->participant_ids;

        return is_array($ids) ? array_values(array_map('strval', $ids)) : [];
    }

    public function hasParticipant(string $userId): bool
    {
        return in_array($userId, $this->participants(), true);
    }
}
