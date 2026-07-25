<?php

namespace App\Features\CreatorTools\Models;

use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreamAnalytics extends Model
{
    use HasUuids;

    protected $table = 'stream_analytics';

    protected $fillable = [
        'room_id',
        'host_id',
        'duration_seconds',
        'peak_viewers',
        'unique_viewers',
        'chat_messages',
        'gifts_count',
        'gifts_coins',
        'new_followers',
        'avg_watch_seconds',
        'timeline',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'peak_viewers' => 'integer',
            'unique_viewers' => 'integer',
            'chat_messages' => 'integer',
            'gifts_count' => 'integer',
            'gifts_coins' => 'integer',
            'new_followers' => 'integer',
            'avg_watch_seconds' => 'float',
            'timeline' => 'array',
            'finalized_at' => 'datetime',
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
