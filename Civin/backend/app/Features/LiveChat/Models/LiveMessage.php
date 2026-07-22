<?php

namespace App\Features\LiveChat\Models;

use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use Database\Factories\LiveMessageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LiveMessage extends Model
{
    /** @use HasFactory<LiveMessageFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    public const TYPE_TEXT = 'TEXT';

    public const TYPE_JOIN = 'JOIN';

    public const TYPE_LEAVE = 'LEAVE';

    public const TYPE_SYSTEM = 'SYSTEM';

    public const TYPE_ADMIN = 'ADMIN';

    public const TYPES = [
        self::TYPE_TEXT,
        self::TYPE_JOIN,
        self::TYPE_LEAVE,
        self::TYPE_SYSTEM,
        self::TYPE_ADMIN,
    ];

    protected $fillable = ['room_id', 'user_id', 'message', 'type', 'metadata'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(LiveRoom::class, 'room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
