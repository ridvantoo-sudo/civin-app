<?php

namespace App\Features\VoiceRoom\Models;

use App\Features\Users\Models\User;
use Database\Factories\VoiceRoomFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VoiceRoom extends Model
{
    /** @use HasFactory<VoiceRoomFactory> */
    use HasFactory, HasUuids;

    public const STATUS_LIVE = 'live';

    public const STATUS_ENDED = 'ended';

    public const STATUSES = [
        self::STATUS_LIVE,
        self::STATUS_ENDED,
    ];

    public const DEFAULT_SEAT_COUNT = 8;

    public $timestamps = false;

    protected $fillable = [
        'host_id',
        'title',
        'description',
        'thumbnail',
        'agora_channel_name',
        'host_uid',
        'status',
        'seat_count',
        'participant_count',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'host_uid' => 'integer',
            'seat_count' => 'integer',
            'participant_count' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function seats(): HasMany
    {
        return $this->hasMany(VoiceSeat::class, 'room_id')->orderBy('seat_index');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(VoiceParticipant::class, 'room_id');
    }

    public function session(): HasOne
    {
        return $this->hasOne(VoiceSession::class, 'room_id');
    }

    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    public function isHost(string $userId): bool
    {
        return $this->host_id === $userId;
    }
}
