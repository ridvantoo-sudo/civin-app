<?php

namespace App\Features\Games\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamePlay extends Model
{
    use HasUuids;

    public const RESULT_WON = 'won';

    public const RESULT_LOST = 'lost';

    public const RESULT_DRAW = 'draw';

    public const RESULT_CLAIMED = 'claimed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PENDING = 'pending';

    public const WALLET_TYPE_GAME_BET = 'GAME_BET';

    public const WALLET_TYPE_GAME_REWARD = 'GAME_REWARD';

    public $timestamps = false;

    protected $fillable = [
        'game_id',
        'user_id',
        'match_id',
        'bet_coins',
        'bet_diamonds',
        'reward_coins',
        'reward_diamonds',
        'result',
        'status',
        'input',
        'outcome',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'bet_coins' => 'integer',
            'bet_diamonds' => 'integer',
            'reward_coins' => 'integer',
            'reward_diamonds' => 'integer',
            'input' => 'array',
            'outcome' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }
}
