<?php

namespace App\Features\VoiceRoom\Models;

use App\Features\Users\Models\User;
use Database\Factories\VoiceParticipantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceParticipant extends Model
{
    /** @use HasFactory<VoiceParticipantFactory> */
    use HasFactory, HasUuids;

    public const ROLE_HOST = 'host';

    public const ROLE_SPEAKER = 'speaker';

    public const ROLE_AUDIENCE = 'audience';

    public const ROLES = [
        self::ROLE_HOST,
        self::ROLE_SPEAKER,
        self::ROLE_AUDIENCE,
    ];

    public $timestamps = false;

    protected $fillable = [
        'room_id',
        'user_id',
        'role',
        'joined_at',
        'left_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
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

    public function isActive(): bool
    {
        return $this->left_at === null;
    }
}
