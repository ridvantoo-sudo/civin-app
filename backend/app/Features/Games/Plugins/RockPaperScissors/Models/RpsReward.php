<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RpsReward extends Model
{
    use HasUuids;

    public const STATUS_GRANTED = 'granted';

    /** Non-wallet reward types settle out of band. */
    public const STATUS_PENDING = 'pending';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    protected $table = 'rps_rewards';

    protected $fillable = [
        'match_id', 'tournament_id', 'user_id', 'config_id',
        'reward_type', 'amount', 'currency', 'reference', 'rank',
        'is_vip', 'is_tournament', 'is_refund', 'status', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_vip' => 'boolean',
            'is_tournament' => 'boolean',
            'is_refund' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(RpsMatch::class, 'match_id');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(RpsTournament::class, 'tournament_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
