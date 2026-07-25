<?php

namespace App\Features\Games\Plugins\Ludo\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LudoMatch extends Model
{
    use HasUuids;

    public const STATUS_WAITING = 'waiting';

    public const STATUS_READY = 'ready';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_FINISHED = 'finished';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FORFEITED = 'forfeited';

    protected $table = 'ludo_matches';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'board_state' => 'array', 'rules' => 'array', 'metadata' => 'array', 'spectators_enabled' => 'boolean', 'replay_enabled' => 'boolean', 'is_paused' => 'boolean', 'turn_deadline_at' => 'datetime', 'ready_deadline_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'settled_at' => 'datetime',
        ];
    }

    public function players(): HasMany
    {
        return $this->hasMany(LudoPlayer::class, 'match_id')->orderBy('seat');
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(LudoConfig::class, 'config_id');
    }

    public function turns(): HasMany
    {
        return $this->hasMany(LudoTurn::class, 'match_id')->orderBy('turn_index');
    }

    public function moves(): HasMany
    {
        return $this->hasMany(LudoMove::class, 'match_id');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(LudoReward::class, 'match_id');
    }

    public function participant(string $userId): ?LudoPlayer
    {
        return $this->players->first(fn (LudoPlayer $player): bool => (string) $player->user_id === $userId);
    }
}
