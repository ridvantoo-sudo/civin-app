<?php

namespace App\Features\Games\Plugins\LuckyNumber\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LuckyNumberReward extends Model
{
    use HasUuids;

    public const STATUS_GRANTED = 'granted';

    public const TYPE_COINS = 'coins';

    public const TYPE_DIAMONDS = 'diamonds';

    public const TYPE_JACKPOT = 'jackpot';

    public const TYPE_BONUS = 'bonus';

    protected $table = 'lucky_number_rewards';

    protected $fillable = [
        'user_id',
        'ticket_id',
        'config_id',
        'reward_type',
        'amount',
        'currency',
        'reference',
        'is_jackpot',
        'is_bonus',
        'is_daily',
        'is_vip',
        'is_streak',
        'is_referral',
        'is_mystery',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'is_jackpot' => 'boolean',
            'is_bonus' => 'boolean',
            'is_daily' => 'boolean',
            'is_vip' => 'boolean',
            'is_streak' => 'boolean',
            'is_referral' => 'boolean',
            'is_mystery' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(LuckyNumberTicket::class, 'ticket_id');
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(LuckyNumberConfig::class, 'config_id');
    }
}
