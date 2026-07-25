<?php

namespace App\Features\Games\Plugins\LuckyNumber\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LuckyNumberJackpot extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const TYPE_ROOM = 'room';

    public const TYPE_GLOBAL = 'global';

    public const TYPE_DAILY = 'daily';

    public const TYPE_VIP = 'vip';

    public const TYPE_PROGRESSIVE = 'progressive';

    public const TYPES = [
        self::TYPE_ROOM,
        self::TYPE_GLOBAL,
        self::TYPE_DAILY,
        self::TYPE_VIP,
        self::TYPE_PROGRESSIVE,
    ];

    protected $table = 'lucky_number_jackpots';

    protected $fillable = [
        'config_id',
        'type',
        'name',
        'pool_coins',
        'seed_coins',
        'min_pool_coins',
        'contribution_percent',
        'requires_vip',
        'room_id',
        'daily_date',
        'status',
        'metadata',
        'last_won_at',
        'last_winner_user_id',
    ];

    protected function casts(): array
    {
        return [
            'pool_coins' => 'integer',
            'seed_coins' => 'integer',
            'min_pool_coins' => 'integer',
            'contribution_percent' => 'float',
            'requires_vip' => 'boolean',
            'daily_date' => 'date',
            'metadata' => 'array',
            'last_won_at' => 'datetime',
        ];
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(LuckyNumberConfig::class, 'config_id');
    }

    public function lastWinner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_winner_user_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
