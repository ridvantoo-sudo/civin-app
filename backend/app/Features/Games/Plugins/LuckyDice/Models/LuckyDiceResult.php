<?php

namespace App\Features\Games\Plugins\LuckyDice\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LuckyDiceResult extends Model
{
    use HasUuids;

    public const RESULT_WON = 'won';

    public const RESULT_LOST = 'lost';

    public const STATUS_SETTLED = 'settled';

    protected $table = 'lucky_dice_results';

    protected $fillable = [
        'bet_id',
        'user_id',
        'session_id',
        'dice_1',
        'dice_2',
        'dice_3',
        'total',
        'is_triple',
        'result',
        'reward_type',
        'reward_amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'dice_1' => 'integer',
            'dice_2' => 'integer',
            'dice_3' => 'integer',
            'total' => 'integer',
            'is_triple' => 'boolean',
            'reward_amount' => 'integer',
        ];
    }

    public function bet(): BelongsTo
    {
        return $this->belongsTo(LuckyDiceBet::class, 'bet_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
