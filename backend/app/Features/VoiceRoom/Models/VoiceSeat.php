<?php

namespace App\Features\VoiceRoom\Models;

use App\Features\Users\Models\User;
use Database\Factories\VoiceSeatFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceSeat extends Model
{
    /** @use HasFactory<VoiceSeatFactory> */
    use HasFactory, HasUuids;

    public const STATUS_EMPTY = 'empty';

    public const STATUS_PENDING = 'pending';

    public const STATUS_OCCUPIED = 'occupied';

    public const STATUSES = [
        self::STATUS_EMPTY,
        self::STATUS_PENDING,
        self::STATUS_OCCUPIED,
    ];

    public const CREATED_AT = null;

    protected $fillable = [
        'room_id',
        'seat_index',
        'user_id',
        'status',
        'is_muted',
        'stream_uid',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'seat_index' => 'integer',
            'is_muted' => 'boolean',
            'stream_uid' => 'integer',
            'updated_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(VoiceRoom::class, 'room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isEmpty(): bool
    {
        return $this->status === self::STATUS_EMPTY;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isOccupied(): bool
    {
        return $this->status === self::STATUS_OCCUPIED;
    }
}
