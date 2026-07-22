<?php

namespace App\Features\LiveStreaming\Models;

use Database\Factories\LiveSessionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveSession extends Model
{
    /** @use HasFactory<LiveSessionFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['room_id', 'duration', 'peak_viewers'];

    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'peak_viewers' => 'integer',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(LiveRoom::class, 'room_id');
    }
}
