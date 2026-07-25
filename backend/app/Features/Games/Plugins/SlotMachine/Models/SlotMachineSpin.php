<?php

namespace App\Features\Games\Plugins\SlotMachine\Models;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlay;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlotMachineSpin extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    protected $table = 'slot_machine_spins';

    protected $fillable = [
        'user_id',
        'config_id',
        'session_id',
        'game_id',
        'play_id',
        'reward_id',
        'wallet_transaction_id',
        'idempotency_key',
        'status',
        'game_mode',
        'bet_amount',
        'spin_count',
        'auto_spin',
        'cost_coins',
        'reward_coins',
        'reward_diamonds',
        'free_spins_awarded',
        'is_win',
        'is_jackpot',
        'is_bonus',
        'is_free_spin',
        'jackpot_type',
        'jackpot_coins',
        'config_version',
        'server_seed',
        'rolls',
        'grid',
        'outcome',
        'metadata',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'bet_amount' => 'integer',
            'spin_count' => 'integer',
            'auto_spin' => 'boolean',
            'cost_coins' => 'integer',
            'reward_coins' => 'integer',
            'reward_diamonds' => 'integer',
            'free_spins_awarded' => 'integer',
            'is_win' => 'boolean',
            'is_jackpot' => 'boolean',
            'is_bonus' => 'boolean',
            'is_free_spin' => 'boolean',
            'jackpot_coins' => 'integer',
            'config_version' => 'integer',
            'rolls' => 'array',
            'grid' => 'array',
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
        return $this->belongsTo(SlotMachineConfig::class, 'config_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SlotMachineSession::class, 'session_id');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function play(): BelongsTo
    {
        return $this->belongsTo(GamePlay::class, 'play_id');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(SlotMachineReward::class, 'spin_id');
    }
}
