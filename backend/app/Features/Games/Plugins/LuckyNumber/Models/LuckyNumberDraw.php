<?php

namespace App\Features\Games\Plugins\LuckyNumber\Models;

use App\Features\Games\Models\Game;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LuckyNumberDraw extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_STARTED = 'started';

    public const STATUS_FINISHED = 'finished';

    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_INSTANT = 'instant';

    public const TYPE_SCHEDULED = 'scheduled';

    public const TYPE_MANUAL = 'manual';

    public const TYPES = [
        self::TYPE_INSTANT,
        self::TYPE_SCHEDULED,
        self::TYPE_MANUAL,
    ];

    protected $table = 'lucky_number_draws';

    protected $fillable = [
        'config_id',
        'game_id',
        'draw_type',
        'game_mode',
        'min_number',
        'max_number',
        'draw_count',
        'status',
        'server_seed',
        'winning_numbers',
        'audit_log',
        'metadata',
        'scheduled_at',
        'started_at',
        'finished_at',
        'triggered_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'min_number' => 'integer',
            'max_number' => 'integer',
            'draw_count' => 'integer',
            'winning_numbers' => 'array',
            'audit_log' => 'array',
            'metadata' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(LuckyNumberConfig::class, 'config_id');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(LuckyNumberTicket::class, 'draw_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(LuckyNumberResult::class, 'draw_id');
    }
}
