<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RpsRound extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_EXPIRED = 'expired';

    public const RESULT_CHALLENGER = 'challenger';

    public const RESULT_OPPONENT = 'opponent';

    public const RESULT_DRAW = 'draw';

    public const RESULT_NO_CONTEST = 'no_contest';

    protected $table = 'rps_rounds';

    protected $fillable = [
        'match_id', 'round_index', 'status', 'result', 'winner_id',
        'challenger_move_id', 'opponent_move_id',
        'challenger_submitted', 'opponent_submitted', 'revealed',
        'challenger_move', 'opponent_move', 'server_seed',
        'audit', 'metadata', 'started_at', 'ends_at', 'resolved_at', 'revealed_at',
    ];

    protected function casts(): array
    {
        return [
            'challenger_submitted' => 'boolean',
            'opponent_submitted' => 'boolean',
            'revealed' => 'boolean',
            'audit' => 'array',
            'metadata' => 'array',
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
            'resolved_at' => 'datetime',
            'revealed_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(RpsMatch::class, 'match_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function moves(): HasMany
    {
        return $this->hasMany(RpsMove::class, 'round_id');
    }

    public function bothSubmitted(): bool
    {
        return $this->challenger_submitted && $this->opponent_submitted;
    }

    public function isExpired(): bool
    {
        return $this->ends_at !== null && now()->greaterThanOrEqualTo($this->ends_at);
    }
}
