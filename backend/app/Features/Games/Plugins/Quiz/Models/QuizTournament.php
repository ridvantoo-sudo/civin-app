<?php

namespace App\Features\Games\Plugins\Quiz\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizTournament extends Model
{
    use HasUuids;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FINISHED = 'finished';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'config_id', 'game_id', 'slug', 'name', 'description', 'status',
        'rounds', 'current_round', 'max_players', 'entry_fee_coins',
        'reward_type', 'reward_pool', 'prize_structure', 'eligibility',
        'metadata', 'scheduled_at', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'prize_structure' => 'array',
            'eligibility' => 'array',
            'metadata' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(QuizConfig::class, 'config_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(QuizSession::class, 'tournament_id');
    }
}
