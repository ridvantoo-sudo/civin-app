<?php

namespace App\Features\Games\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameReward extends Model
{
    use HasUuids;

    public const STATUS_GRANTED = 'granted';

    public const STATUS_PENDING_FULFILLMENT = 'pending_fulfillment';

    public const STATUS_FULFILLED = 'fulfilled';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'game_id',
        'play_id',
        'session_id',
        'coins',
        'diamonds',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'coins' => 'integer',
            'diamonds' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function play(): BelongsTo
    {
        return $this->belongsTo(GamePlay::class, 'play_id');
    }
}
