<?php

namespace App\Features\Games\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LotteryTicket extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_WON = 'won';

    public const STATUS_LOST = 'lost';

    public $timestamps = false;

    protected $fillable = [
        'round_id',
        'user_id',
        'numbers',
        'coins_paid',
        'reward_coins',
        'status',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'numbers' => 'array',
            'coins_paid' => 'integer',
            'reward_coins' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(LotteryRound::class, 'round_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
