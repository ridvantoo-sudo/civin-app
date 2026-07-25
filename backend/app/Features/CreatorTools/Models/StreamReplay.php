<?php

namespace App\Features\CreatorTools\Models;

use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreamReplay extends Model
{
    use HasUuids;

    protected $fillable = [
        'room_id',
        'host_id',
        'title',
        'status',
        'duration_seconds',
        'peak_viewers',
        'media_url',
        'thumbnail_url',
        'views_count',
        'expires_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'peak_viewers' => 'integer',
            'views_count' => 'integer',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(LiveRoom::class, 'room_id');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }
}
