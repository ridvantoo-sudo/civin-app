<?php

namespace App\Features\Games\Plugins\LuckyNumber\Models;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlay;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LuckyNumberTicket extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    protected $table = 'lucky_number_tickets';

    protected $fillable = [
        'user_id',
        'config_id',
        'draw_id',
        'game_id',
        'play_id',
        'reward_id',
        'wallet_transaction_id',
        'idempotency_key',
        'status',
        'game_mode',
        'min_number',
        'max_number',
        'picked_numbers',
        'auto_pick',
        'repeat_ticket',
        'bet_amount',
        'cost_coins',
        'reward_coins',
        'reward_diamonds',
        'is_win',
        'is_jackpot',
        'is_bonus',
        'jackpot_type',
        'jackpot_coins',
        'config_version',
        'server_seed',
        'outcome',
        'metadata',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'min_number' => 'integer',
            'max_number' => 'integer',
            'picked_numbers' => 'array',
            'auto_pick' => 'boolean',
            'repeat_ticket' => 'boolean',
            'bet_amount' => 'integer',
            'cost_coins' => 'integer',
            'reward_coins' => 'integer',
            'reward_diamonds' => 'integer',
            'is_win' => 'boolean',
            'is_jackpot' => 'boolean',
            'is_bonus' => 'boolean',
            'jackpot_coins' => 'integer',
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
        return $this->belongsTo(LuckyNumberConfig::class, 'config_id');
    }

    public function draw(): BelongsTo
    {
        return $this->belongsTo(LuckyNumberDraw::class, 'draw_id');
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
        return $this->hasMany(LuckyNumberReward::class, 'ticket_id');
    }

    public function result(): HasOne
    {
        return $this->hasOne(LuckyNumberResult::class, 'ticket_id');
    }
}
