<?php

namespace App\Features\VoiceRoom\Models;

use Database\Factories\VoiceSessionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceSession extends Model
{
    /** @use HasFactory<VoiceSessionFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'room_id',
        'duration',
        'peak_participants',
    ];

    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'peak_participants' => 'integer',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(VoiceRoom::class, 'room_id');
    }
}
