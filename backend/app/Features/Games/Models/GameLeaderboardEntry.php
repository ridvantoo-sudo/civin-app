<?php

namespace App\Features\Games\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameLeaderboardEntry extends Model
{
    use HasUuids;

    public const PERIOD_DAILY = 'daily';

    public const PERIOD_WEEKLY = 'weekly';

    public const PERIOD_ALL = 'all';

    protected $fillable = [
        'user_id',
        'game_id',
        'period',
        'period_key',
        'plays',
        'wins',
        'coins_wagered',
        'coins_net',
        'score',
    ];

    protected function casts(): array
    {
        return [
            'plays' => 'integer',
            'wins' => 'integer',
            'coins_wagered' => 'integer',
            'coins_net' => 'integer',
            'score' => 'integer',
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
}
