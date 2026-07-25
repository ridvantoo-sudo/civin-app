<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RpsBracket extends Model
{
    use HasUuids;

    public const TYPE_WINNERS = 'winners';

    public const TYPE_LOSERS = 'losers';

    public const TYPE_FINAL = 'final';

    public const TYPE_ROUND_ROBIN = 'round_robin';

    public const STATUS_PENDING = 'pending';

    public const STATUS_READY = 'ready';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FINISHED = 'finished';

    public const STATUS_BYE = 'bye';

    protected $table = 'rps_brackets';

    protected $fillable = [
        'tournament_id', 'match_id', 'bracket_type', 'round_index', 'slot_index', 'status',
        'player_one_id', 'player_two_id', 'winner_id', 'loser_id',
        'next_bracket_id', 'next_loser_bracket_id', 'is_bye', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_bye' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(RpsTournament::class, 'tournament_id');
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(RpsMatch::class, 'match_id');
    }

    public function isReady(): bool
    {
        return $this->player_one_id !== null && $this->player_two_id !== null;
    }
}
