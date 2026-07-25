<?php

namespace App\Features\Games\Plugins\LuckyWheel\Models;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlay;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LuckyWheelSpin extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_FAILED = 'failed';

    public const STATUS_FULFILLMENT_PENDING = 'fulfillment_pending';

    protected $table = 'lucky_wheel_spins';

    protected $fillable = [
        'user_id',
        'config_id',
        'sector_id',
        'game_id',
        'play_id',
        'reward_id',
        'wallet_transaction_id',
        'idempotency_key',
        'status',
        'cost_coins',
        'reward_type',
        'reward_amount',
        'reward_currency',
        'reward_reference',
        'is_jackpot',
        'sector_position',
        'sector_label',
        'config_version',
        'server_seed',
        'roll',
        'total_weight',
        'outcome',
        'metadata',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'cost_coins' => 'integer',
            'reward_amount' => 'integer',
            'is_jackpot' => 'boolean',
            'sector_position' => 'integer',
            'config_version' => 'integer',
            'roll' => 'integer',
            'total_weight' => 'integer',
            'outcome' => 'array',
            'metadata' => 'array',
            'settled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(LuckyWheelConfig::class, 'config_id');
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(LuckyWheelSector::class, 'sector_id');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function play(): BelongsTo
    {
        return $this->belongsTo(GamePlay::class, 'play_id');
    }
}
