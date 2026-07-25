<?php

namespace App\Features\Games\Plugins\LuckyDice\Models;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlay;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LuckyDiceBet extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_FAILED = 'failed';

    public const STATUS_FULFILLMENT_PENDING = 'fulfillment_pending';

    public const REWARD_TYPE_COINS = 'coins';

    public const REWARD_TYPE_DIAMONDS = 'diamonds';

    public const REWARD_TYPE_GIFT = 'gift';

    public const REWARD_TYPE_VIP = 'vip';

    public const REWARD_TYPE_FRAME = 'frame';

    public const REWARD_TYPE_ENTRY_EFFECT = 'entry_effect';

    public const REWARD_TYPE_JACKPOT = 'jackpot';

    public const REWARD_TYPE_NONE = 'none';

    public const MODE_BIG_SMALL = 'big_small';

    public const MODE_ODD_EVEN = 'odd_even';

    public const MODE_EXACT_TOTAL = 'exact_total';

    public const MODE_TRIPLE_MATCH = 'triple_match';

    /** @var list<string> */
    public const MODES = [
        self::MODE_BIG_SMALL,
        self::MODE_ODD_EVEN,
        self::MODE_EXACT_TOTAL,
        self::MODE_TRIPLE_MATCH,
    ];

    protected $table = 'lucky_dice_bets';

    protected $fillable = [
        'user_id',
        'config_id',
        'game_id',
        'play_id',
        'reward_id',
        'wallet_transaction_id',
        'session_id',
        'idempotency_key',
        'bet_mode',
        'prediction',
        'bet_amount',
        'status',
        'reward_type',
        'reward_amount',
        'reward_currency',
        'reward_reference',
        'is_jackpot',
        'is_win',
        'multiplier',
        'result_id',
        'config_version',
        'server_seed',
        'outcome',
        'metadata',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'bet_amount' => 'integer',
            'reward_amount' => 'integer',
            'is_jackpot' => 'boolean',
            'is_win' => 'boolean',
            'multiplier' => 'float',
            'config_version' => 'integer',
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
        return $this->belongsTo(LuckyDiceConfig::class, 'config_id');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function play(): BelongsTo
    {
        return $this->belongsTo(GamePlay::class, 'play_id');
    }

    public function result(): HasOne
    {
        return $this->hasOne(LuckyDiceResult::class, 'bet_id');
    }

    public function isWalletReward(): bool
    {
        return in_array($this->reward_type, [
            self::REWARD_TYPE_COINS,
            self::REWARD_TYPE_DIAMONDS,
            self::REWARD_TYPE_JACKPOT,
        ], true);
    }

    public function requiresFulfillment(): bool
    {
        return in_array($this->reward_type, [
            self::REWARD_TYPE_GIFT,
            self::REWARD_TYPE_VIP,
            self::REWARD_TYPE_FRAME,
            self::REWARD_TYPE_ENTRY_EFFECT,
        ], true);
    }
}
