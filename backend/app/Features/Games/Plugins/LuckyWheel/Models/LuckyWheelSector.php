<?php

namespace App\Features\Games\Plugins\LuckyWheel\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LuckyWheelSector extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const REWARD_TYPE_COINS = 'coins';

    public const REWARD_TYPE_DIAMONDS = 'diamonds';

    public const REWARD_TYPE_NONE = 'none';

    public const REWARD_TYPE_ITEM = 'item';

    public const REWARD_TYPE_GIFT = 'gift';

    public const REWARD_TYPE_VIP = 'vip';

    public const REWARD_TYPE_CUSTOM = 'custom';

    protected $table = 'lucky_wheel_sectors';

    protected $fillable = [
        'config_id',
        'position',
        'label',
        'reward_type',
        'reward_amount',
        'reward_reference',
        'weight',
        'color',
        'icon',
        'is_jackpot',
        'requires_fulfillment',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'reward_amount' => 'integer',
            'weight' => 'integer',
            'is_jackpot' => 'boolean',
            'requires_fulfillment' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(LuckyWheelConfig::class, 'config_id');
    }

    public function spins(): HasMany
    {
        return $this->hasMany(LuckyWheelSpin::class, 'sector_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isWalletReward(): bool
    {
        return in_array($this->reward_type, [self::REWARD_TYPE_COINS, self::REWARD_TYPE_DIAMONDS], true);
    }
}
