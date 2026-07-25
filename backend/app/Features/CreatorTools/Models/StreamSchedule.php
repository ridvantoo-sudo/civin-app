<?php

namespace App\Features\CreatorTools\Models;

use App\Features\LiveStreaming\Models\LiveCategory;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreamSchedule extends Model
{
    use HasUuids;

    protected $fillable = [
        'host_id',
        'category_id',
        'room_id',
        'title',
        'description',
        'thumbnail',
        'status',
        'scheduled_at',
        'timezone',
        'reminded_at',
        'started_at',
        'cancelled_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'reminded_at' => 'datetime',
            'started_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(LiveCategory::class, 'category_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(LiveRoom::class, 'room_id');
    }
}
