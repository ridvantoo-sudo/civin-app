<?php

namespace App\Features\LiveChat\Models;

use App\Features\LiveStreaming\Models\LiveRoom;
use Database\Factories\LiveChatSettingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveChatSetting extends Model
{
    /** @use HasFactory<LiveChatSettingFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'room_id',
        'slow_mode_seconds',
        'followers_only',
        'allow_links',
    ];

    protected function casts(): array
    {
        return [
            'slow_mode_seconds' => 'integer',
            'followers_only' => 'boolean',
            'allow_links' => 'boolean',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(LiveRoom::class, 'room_id');
    }
}
