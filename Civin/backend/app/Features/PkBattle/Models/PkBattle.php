<?php

namespace App\Features\PkBattle\Models;

use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use Database\Factories\PkBattleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PkBattle extends Model
{
    /** @use HasFactory<PkBattleFactory> */
    use HasFactory, HasUuids;

    public const STATUS_WAITING = 'WAITING';

    public const STATUS_RUNNING = 'RUNNING';

    public const STATUS_FINISHED = 'FINISHED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const STATUSES = [
        self::STATUS_WAITING,
        self::STATUS_RUNNING,
        self::STATUS_FINISHED,
        self::STATUS_CANCELLED,
    ];

    public const ACTIVE_STATUSES = [
        self::STATUS_WAITING,
        self::STATUS_RUNNING,
    ];

    public const DEFAULT_DURATION_SECONDS = 180;

    public $timestamps = false;

    protected $fillable = [
        'room_a_id',
        'room_b_id',
        'host_a_id',
        'host_b_id',
        'status',
        'duration_seconds',
        'started_at',
        'ended_at',
        'winner_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function roomA(): BelongsTo
    {
        return $this->belongsTo(LiveRoom::class, 'room_a_id');
    }

    public function roomB(): BelongsTo
    {
        return $this->belongsTo(LiveRoom::class, 'room_b_id');
    }

    public function hostA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_a_id');
    }

    public function hostB(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_b_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(PkScore::class, 'pk_battle_id');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(PkReward::class, 'pk_battle_id');
    }

    public function involvesRoom(string $roomId): bool
    {
        return $this->room_a_id === $roomId || $this->room_b_id === $roomId;
    }

    public function involvesHost(string $userId): bool
    {
        return $this->host_a_id === $userId || $this->host_b_id === $userId;
    }

    public function opponentRoomId(string $roomId): ?string
    {
        if ($this->room_a_id === $roomId) {
            return $this->room_b_id;
        }

        if ($this->room_b_id === $roomId) {
            return $this->room_a_id;
        }

        return null;
    }
}
