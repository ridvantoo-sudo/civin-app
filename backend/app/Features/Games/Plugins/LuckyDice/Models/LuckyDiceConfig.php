<?php

namespace App\Features\Games\Plugins\LuckyDice\Models;

use App\Features\Games\Models\Game;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LuckyDiceConfig extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const MODE_BIG_SMALL = 'big_small';

    public const MODE_ODD_EVEN = 'odd_even';

    public const MODE_EXACT_TOTAL = 'exact_total';

    public const MODE_TRIPLE_MATCH = 'triple_match';

    protected $table = 'lucky_dice_configs';

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
        'max_plays_per_day',
        'cooldown_seconds',
        'countdown_seconds',
        'multiplier_big_small',
        'multiplier_odd_even',
        'multiplier_triple',
        'exact_total_multipliers',
        'jackpot_enabled',
        'jackpot_coins',
        'jackpot_on_triple',
        'modes_enabled',
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
            'max_plays_per_day' => 'integer',
            'cooldown_seconds' => 'integer',
            'countdown_seconds' => 'integer',
            'multiplier_big_small' => 'float',
            'multiplier_odd_even' => 'float',
            'multiplier_triple' => 'float',
            'exact_total_multipliers' => 'array',
            'jackpot_enabled' => 'boolean',
            'jackpot_coins' => 'integer',
            'jackpot_on_triple' => 'boolean',
            'modes_enabled' => 'array',
            'is_default' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function bets(): HasMany
    {
        return $this->hasMany(LuckyDiceBet::class, 'config_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function modeEnabled(string $mode): bool
    {
        $modes = $this->modes_enabled ?? [];

        return in_array($mode, $modes, true);
    }
}
