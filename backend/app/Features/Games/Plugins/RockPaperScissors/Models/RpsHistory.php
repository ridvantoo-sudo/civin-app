<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RpsHistory extends Model
{
    use HasUuids;

    protected $table = 'rps_histories';

    protected $fillable = [
        'user_id', 'match_id', 'tournament_id', 'config_id', 'game_id', 'play_id', 'opponent_id',
        'mode', 'matchmaking', 'result', 'rounds_won', 'rounds_lost', 'rounds_drawn',
        'entry_fee_coins', 'reward_coins', 'reward_diamonds', 'is_winner', 'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'is_winner' => 'boolean',
            'snapshot' => 'array',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(RpsMatch::class, 'match_id');
    }
}
