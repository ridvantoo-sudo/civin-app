<?php

namespace App\Features\Games\Plugins\LuckyNumber\Models;

use App\Features\Games\Models\Game;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LuckyNumberConfig extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const MODE_SINGLE_NUMBER = 'single_number';

    public const MODE_MULTIPLE_NUMBERS = 'multiple_numbers';

    public const MODE_LUCKY_PICK = 'lucky_pick';

    public const MODE_QUICK_BET = 'quick_bet';

    public const MODE_VIP_LUCKY_NUMBERS = 'vip_lucky_numbers';

    public const MODE_DAILY_LUCKY_NUMBER = 'daily_lucky_number';

    public const MODE_PROGRESSIVE_JACKPOT = 'progressive_jackpot';

    public const MODES = [
        self::MODE_SINGLE_NUMBER,
        self::MODE_MULTIPLE_NUMBERS,
        self::MODE_LUCKY_PICK,
        self::MODE_QUICK_BET,
        self::MODE_VIP_LUCKY_NUMBERS,
        self::MODE_DAILY_LUCKY_NUMBER,
        self::MODE_PROGRESSIVE_JACKPOT,
    ];

    /** @var list<array{0: int, 1: int}> */
    public const RANGE_PRESETS = [
        [1, 10],
        [1, 20],
        [1, 50],
        [1, 100],
    ];

    protected $table = 'lucky_number_configs';

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
        'number_ranges',
        'mode_ranges',
        'reward_multipliers',
        'default_min_number',
        'default_max_number',
        'max_picks',
        'draw_count',
        'rtp_percent',
        'house_edge_percent',
        'max_tickets_per_day',
        'cooldown_seconds',
        'progressive_contribution_percent',
        'jackpot_enabled',
        'bonus_enabled',
        'vip_reward_multiplier',
        'bonus_probabilities',
        'draw_interval_seconds',
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
            'number_ranges' => 'array',
            'mode_ranges' => 'array',
            'reward_multipliers' => 'array',
            'default_min_number' => 'integer',
            'default_max_number' => 'integer',
            'max_picks' => 'integer',
            'draw_count' => 'integer',
            'rtp_percent' => 'float',
            'house_edge_percent' => 'float',
            'max_tickets_per_day' => 'integer',
            'cooldown_seconds' => 'integer',
            'progressive_contribution_percent' => 'float',
            'jackpot_enabled' => 'boolean',
            'bonus_enabled' => 'boolean',
            'vip_reward_multiplier' => 'float',
            'bonus_probabilities' => 'array',
            'draw_interval_seconds' => 'integer',
            'is_default' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function jackpots(): HasMany
    {
        return $this->hasMany(LuckyNumberJackpot::class, 'config_id');
    }

    public function draws(): HasMany
    {
        return $this->hasMany(LuckyNumberDraw::class, 'config_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(LuckyNumberTicket::class, 'config_id');
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

    /**
     * @return array{min: int, max: int}
     */
    public function rangeForMode(string $mode): array
    {
        $modeRanges = $this->mode_ranges ?? [];
        $range = $modeRanges[$mode] ?? null;

        if (is_array($range)) {
            if (isset($range['min'], $range['max'])) {
                return [
                    'min' => (int) $range['min'],
                    'max' => (int) $range['max'],
                ];
            }

            if (isset($range[0], $range[1])) {
                return [
                    'min' => (int) $range[0],
                    'max' => (int) $range[1],
                ];
            }
        }

        return [
            'min' => (int) $this->default_min_number,
            'max' => (int) $this->default_max_number,
        ];
    }
}
