<?php

namespace App\Features\Games\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameHistory extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'game_histories';

    protected $fillable = [
        'user_id',
        'game_id',
        'play_id',
        'result',
        'bet_coins',
        'reward_coins',
        'snapshot',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'bet_coins' => 'integer',
            'reward_coins' => 'integer',
            'snapshot' => 'array',
            'created_at' => 'datetime',
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
