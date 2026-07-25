<?php

namespace App\Features\Challenges\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Challenge extends Model
{
    use HasUuids;

    public const PERIOD_DAILY = 'daily';

    public const PERIOD_WEEKLY = 'weekly';

    public const PERIODS = [
        self::PERIOD_DAILY,
        self::PERIOD_WEEKLY,
    ];

    public const TASK_LOGIN = 'login';

    public const TASK_WATCH = 'watching';

    public const TASK_LIVE = 'live';

    public const TASK_GIFT_SEND = 'gift_send';

    public const TASK_GIFT_RECEIVE = 'gift_receive';

    public const TASK_PK = 'pk';

    public const TASK_TYPES = [
        self::TASK_LOGIN,
        self::TASK_WATCH,
        self::TASK_LIVE,
        self::TASK_GIFT_SEND,
        self::TASK_GIFT_RECEIVE,
        self::TASK_PK,
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'code',
        'title',
        'description',
        'period',
        'task_type',
        'target',
        'reward_xp',
        'reward_coins',
        'reward_diamonds',
        'icon',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'target' => 'integer',
            'reward_xp' => 'integer',
            'reward_coins' => 'integer',
            'reward_diamonds' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function progressRows(): HasMany
    {
        return $this->hasMany(UserChallengeProgress::class, 'challenge_id');
    }

    public function isDaily(): bool
    {
        return $this->period === self::PERIOD_DAILY;
    }

    public function isWeekly(): bool
    {
        return $this->period === self::PERIOD_WEEKLY;
    }
}
