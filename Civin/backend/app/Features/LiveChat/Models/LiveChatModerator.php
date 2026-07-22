<?php

namespace App\Features\LiveChat\Models;

use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use Database\Factories\LiveChatModeratorFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveChatModerator extends Model
{
    /** @use HasFactory<LiveChatModeratorFactory> */
    use HasFactory, HasUuids;

    public const ROLE_MODERATOR = 'moderator';

    public const ROLE_ADMIN = 'admin';

    protected $fillable = ['room_id', 'user_id', 'role'];

    public function room(): BelongsTo
    {
        return $this->belongsTo(LiveRoom::class, 'room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
