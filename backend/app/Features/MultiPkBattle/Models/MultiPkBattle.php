<?php

namespace App\Features\MultiPkBattle\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class MultiPkBattle extends Model
{
    use HasUuids;

    public const MODE_TEAM_2V2 = 'TEAM_2V2';

    public const MODE_FFA_4 = 'FFA_4';

    public const STATUS_WAITING = 'WAITING';

    public const STATUS_RUNNING = 'RUNNING';

    public const STATUS_FINISHED = 'FINISHED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const DEFAULT_DURATION_SECONDS = 180;

    public const DEFAULT_COUNTDOWN_SECONDS = 3;

    public const DEFAULT_INVITATION_SECONDS = 15;

    protected $fillable = [
        'initiator_id', 'mode', 'status', 'duration_seconds', 'countdown_seconds',
        'invitation_expires_at', 'started_at', 'ended_at', 'winner_id', 'winning_team',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'countdown_seconds' => 'integer',
            'invitation_expires_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'winning_team' => 'integer',
        ];
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MultiPkParticipant::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function involves(string $userId): bool
    {
        return $this->participants()->where('user_id', $userId)->exists();
    }
}
