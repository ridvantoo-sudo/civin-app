<?php

namespace App\Features\Games\Plugins\SlotMachine\Models;

use App\Features\Games\Models\Game;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlotMachineConfig extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const MODE_CLASSIC = 'classic';

    public const MODE_PREMIUM = 'premium';

    public const MODE_VIP = 'vip';

    public const MODE_PROGRESSIVE_JACKPOT = 'progressive_jackpot';

    public const MODE_DAILY_JACKPOT = 'daily_jackpot';

    public const MODE_FREE_SPIN = 'free_spin';

    public const MODE_BONUS_SPIN = 'bonus_spin';

    public const MODES = [
        self::MODE_CLASSIC,
        self::MODE_PREMIUM,
        self::MODE_VIP,
        self::MODE_PROGRESSIVE_JACKPOT,
        self::MODE_DAILY_JACKPOT,
        self::MODE_FREE_SPIN,
        self::MODE_BONUS_SPIN,
    ];

    protected $table = 'slot_machine_configs';

    protected $fillable = [
        'game_id',
        'slug',
        'name',
        'description',
        'version',
        'min_bet_coins',
        'max_bet_coins',
        'default_bet_coins',
        'chip_amounts',
        'modes_enabled',
        'rtp_percent',
        'house_edge_percent',
        'classic_columns',
        'classic_rows',
        'premium_columns',
        'premium_rows',
        'max_spins_per_day',
        'cooldown_seconds',
        'daily_free_spins',
        'max_auto_spins',
        'max_spin_count',
        'free_spin_award_on_scatters',
        'free_spins_granted',
        'progressive_contribution_percent',
        'jackpot_enabled',
        'bonus_enabled',
        'bonus_trigger_symbols',
        'vip_reward_multiplier',
        'bonus_probabilities',
        'status',
        'is_default',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'min_bet_coins' => 'integer',
            'max_bet_coins' => 'integer',
            'default_bet_coins' => 'integer',
            'chip_amounts' => 'array',
            'modes_enabled' => 'array',
            'rtp_percent' => 'float',
            'house_edge_percent' => 'float',
            'classic_columns' => 'integer',
            'classic_rows' => 'integer',
            'premium_columns' => 'integer',
            'premium_rows' => 'integer',
            'max_spins_per_day' => 'integer',
            'cooldown_seconds' => 'integer',
            'daily_free_spins' => 'integer',
            'max_auto_spins' => 'integer',
            'max_spin_count' => 'integer',
            'free_spin_award_on_scatters' => 'integer',
            'free_spins_granted' => 'integer',
            'progressive_contribution_percent' => 'float',
            'jackpot_enabled' => 'boolean',
            'bonus_enabled' => 'boolean',
            'bonus_trigger_symbols' => 'integer',
            'vip_reward_multiplier' => 'float',
            'bonus_probabilities' => 'array',
            'is_default' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function symbols(): HasMany
    {
        return $this->hasMany(SlotMachineSymbol::class, 'config_id')->orderBy('sort_order');
    }

    public function paylines(): HasMany
    {
        return $this->hasMany(SlotMachinePayline::class, 'config_id')->orderBy('sort_order');
    }

    public function jackpots(): HasMany
    {
        return $this->hasMany(SlotMachineJackpot::class, 'config_id');
    }

    public function spins(): HasMany
    {
        return $this->hasMany(SlotMachineSpin::class, 'config_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function modeEnabled(string $mode): bool
    {
        $modes = $this->modes_enabled ?? self::MODES;

        return in_array($mode, $modes, true);
    }
}
