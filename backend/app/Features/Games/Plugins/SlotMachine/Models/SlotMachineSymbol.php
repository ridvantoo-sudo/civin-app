<?php

namespace App\Features\Games\Plugins\SlotMachine\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlotMachineSymbol extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const CODE_CHERRY = 'cherry';

    public const CODE_SEVEN = 'seven';

    public const CODE_DIAMOND = 'diamond';

    public const CODE_GOLD_BAR = 'gold_bar';

    public const CODE_COIN = 'coin';

    public const CODE_STAR = 'star';

    public const CODE_TREASURE_CHEST = 'treasure_chest';

    public const CODE_CROWN = 'crown';

    public const CODE_WILD = 'wild';

    public const CODE_SCATTER = 'scatter';

    public const CODE_JACKPOT = 'jackpot';

    public const CODE_BONUS = 'bonus';

    public const CODES = [
        self::CODE_CHERRY,
        self::CODE_SEVEN,
        self::CODE_DIAMOND,
        self::CODE_GOLD_BAR,
        self::CODE_COIN,
        self::CODE_STAR,
        self::CODE_TREASURE_CHEST,
        self::CODE_CROWN,
        self::CODE_WILD,
        self::CODE_SCATTER,
        self::CODE_JACKPOT,
        self::CODE_BONUS,
    ];

    protected $table = 'slot_machine_symbols';

    protected $fillable = [
        'config_id',
        'code',
        'label',
        'icon',
        'color',
        'weight',
        'payout_3',
        'payout_4',
        'payout_5',
        'is_wild',
        'is_scatter',
        'is_bonus',
        'is_jackpot',
        'is_multiplier',
        'multiplier_value',
        'sort_order',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'payout_3' => 'integer',
            'payout_4' => 'integer',
            'payout_5' => 'integer',
            'is_wild' => 'boolean',
            'is_scatter' => 'boolean',
            'is_bonus' => 'boolean',
            'is_jackpot' => 'boolean',
            'is_multiplier' => 'boolean',
            'multiplier_value' => 'integer',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(SlotMachineConfig::class, 'config_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function payoutForCount(int $count): int
    {
        return match ($count) {
            5 => (int) $this->payout_5,
            4 => (int) $this->payout_4,
            3 => (int) $this->payout_3,
            default => 0,
        };
    }
}
