<?php

namespace App\Features\Games\Plugins\Quiz\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizReward extends Model
{
    use HasUuids;

    public const STATUS_GRANTED = 'granted';

    public const STATUS_PENDING = 'pending';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'session_id', 'user_id', 'player_id', 'score_id', 'config_id',
        'reward_type', 'amount', 'currency', 'reference', 'rank',
        'is_jackpot', 'is_mystery', 'is_vip', 'status', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_jackpot' => 'boolean',
            'is_mystery' => 'boolean',
            'is_vip' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class, 'session_id');
    }
}
