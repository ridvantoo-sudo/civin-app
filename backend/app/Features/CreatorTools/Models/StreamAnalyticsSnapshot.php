<?php

namespace App\Features\CreatorTools\Models;

use App\Features\LiveStreaming\Models\LiveRoom;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreamAnalyticsSnapshot extends Model
{
    use HasUuids;

    protected $fillable = [
        'room_id',
        'viewer_count',
        'chat_messages',
        'gifts_count',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'viewer_count' => 'integer',
            'chat_messages' => 'integer',
            'gifts_count' => 'integer',
            'captured_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(LiveRoom::class, 'room_id');
    }
}
