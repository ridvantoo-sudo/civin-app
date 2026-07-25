<?php

namespace App\Features\CreatorTools\Models;

use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreamClip extends Model
{
    use HasUuids;

    protected $fillable = [
        'room_id',
        'host_id',
        'created_by',
        'title',
        'kind',
        'start_seconds',
        'end_seconds',
        'duration_seconds',
        'status',
        'media_url',
        'thumbnail_url',
        'views_count',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'start_seconds' => 'integer',
            'end_seconds' => 'integer',
            'duration_seconds' => 'integer',
            'views_count' => 'integer',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
