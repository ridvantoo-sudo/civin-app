<?php

namespace App\Features\Games\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreasureBoxStatistic extends Model
{
    use HasUuids;

    public const SCOPE_USER = 'user';

    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_ROOM = 'room';

    protected $fillable = [
        'user_id',
        'scope',
        'treasures_created',
        'treasures_joined',
        'treasures_won',
        'total_reward_amount',
        'total_spent_amount',
        'jackpots_won',
        'average_join_seconds',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'treasures_created' => 'integer',
            'treasures_joined' => 'integer',
            'treasures_won' => 'integer',
            'total_reward_amount' => 'integer',
            'total_spent_amount' => 'integer',
            'jackpots_won' => 'integer',
            'average_join_seconds' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
