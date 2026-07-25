<?php

namespace App\Features\VoicePkBattle\Models;

use App\Features\Users\Models\User;
use App\Features\VoiceRoom\Models\VoiceRoom;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class VoicePkBattle extends Model
{
    use HasUuids;

    public const STATUS_WAITING = 'WAITING';

    public const STATUS_RUNNING = 'RUNNING';

    public const STATUS_FINISHED = 'FINISHED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const ACTIVE_STATUSES = [self::STATUS_WAITING, self::STATUS_RUNNING];

    public const DEFAULT_DURATION_SECONDS = 180;

    public const DEFAULT_COUNTDOWN_SECONDS = 3;

    public const DEFAULT_INVITATION_SECONDS = 15;

    protected $fillable = [
        'initiator_id', 'status', 'duration_seconds', 'countdown_seconds',
        'invitation_expires_at', 'countdown_started_at', 'started_at', 'ends_at',
        'ended_at', 'winner_room_id', 'is_draw',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'countdown_seconds' => 'integer',
            'invitation_expires_at' => 'datetime',
            'countdown_started_at' => 'datetime',
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
            'ended_at' => 'datetime',
            'is_draw' => 'boolean',
        ];
    }

    public function teams(): HasMany
    {
        return $this->hasMany(VoicePkTeam::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function winnerRoom(): BelongsTo
    {
        return $this->belongsTo(VoiceRoom::class, 'winner_room_id');
    }

    public function involves(string $userId): bool
    {
        return $this->teams()->where('host_id', $userId)->exists();
    }

    public function toArray(): array
    {
        $this->loadMissing(['teams.room', 'teams.host.profile']);
        $roomA = $this->teams->firstWhere('host_id', $this->initiator_id);
        $roomB = $this->teams->first(
            fn (VoicePkTeam $team): bool => $team->host_id !== $this->initiator_id,
        );

        return [
            ...parent::toArray(),
            'room_a' => $roomA?->toArray(),
            'room_b' => $roomB?->toArray(),
            'starts_at' => $this->started_at?->toISOString(),
        ];
    }
}
