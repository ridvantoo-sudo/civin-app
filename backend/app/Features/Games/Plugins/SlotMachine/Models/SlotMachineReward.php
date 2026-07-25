<?php

namespace App\Features\Games\Plugins\SlotMachine\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlotMachineReward extends Model
{
    use HasUuids;

    public const STATUS_GRANTED = 'granted';

    public const STATUS_PENDING = 'pending';

    public const TYPE_COINS = 'coins';

    public const TYPE_DIAMONDS = 'diamonds';

    public const TYPE_FREE_SPINS = 'free_spins';

    public const TYPE_BONUS = 'bonus';

    public const TYPE_JACKPOT = 'jackpot';

    public const TYPE_COMBO = 'combo';

    public const TYPE_DAILY = 'daily';

    public const TYPE_VIP = 'vip';

    public const TYPE_MYSTERY = 'mystery';

    protected $table = 'slot_machine_rewards';

    protected $fillable = [
        'user_id',
        'spin_id',
        'config_id',
        'reward_type',
        'amount',
        'currency',
        'reference',
        'is_jackpot',
        'is_bonus',
        'is_combo',
        'is_daily',
        'is_vip',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'is_jackpot' => 'boolean',
            'is_bonus' => 'boolean',
            'is_combo' => 'boolean',
            'is_daily' => 'boolean',
            'is_vip' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function spin(): BelongsTo
    {
        return $this->belongsTo(SlotMachineSpin::class, 'spin_id');
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(SlotMachineConfig::class, 'config_id');
    }
}
