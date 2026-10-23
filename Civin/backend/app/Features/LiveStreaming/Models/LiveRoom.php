<?php

namespace App\Features\LiveStreaming\Models;

use App\Features\Gifts\Models\GiftTransaction;
use App\Features\LiveChat\Models\LiveChatModerator;
use App\Features\LiveChat\Models\LiveChatSetting;
use App\Features\LiveChat\Models\LiveMessage;
use App\Features\Users\Models\User;
use Database\Factories\LiveRoomFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LiveRoom extends Model
{
    /** @use HasFactory<LiveRoomFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'host_id', 'category_id', 'title', 'description', 'thumbnail',
        'agora_channel_name', 'stream_uid', 'status', 'viewer_count', 'started_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'stream_uid' => 'integer',
            'viewer_count' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
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

    public function viewers(): HasMany
    {
        return $this->hasMany(LiveViewer::class, 'room_id');
    }

    public function session(): HasOne
    {
        return $this->hasOne(LiveSession::class, 'room_id');
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(LiveMessage::class, 'room_id');
    }

    public function chatSettings(): HasOne
    {
        return $this->hasOne(LiveChatSetting::class, 'room_id');
    }

    public function chatModerators(): HasMany
    {
        return $this->hasMany(LiveChatModerator::class, 'room_id');
    }

    public function giftTransactions(): HasMany
    {
        return $this->hasMany(GiftTransaction::class, 'room_id');
    }
}
