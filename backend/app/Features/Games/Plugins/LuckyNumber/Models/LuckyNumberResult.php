<?php

namespace App\Features\Games\Plugins\LuckyNumber\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LuckyNumberResult extends Model
{
    use HasUuids;

    protected $table = 'lucky_number_results';

    protected $fillable = [
        'ticket_id',
        'draw_id',
        'user_id',
        'config_id',
        'picked_numbers',
        'winning_numbers',
        'match_count',
        'multiplier',
        'reward_coins',
        'is_win',
        'is_jackpot',
        'audit',
    ];

    protected function casts(): array
    {
        return [
            'picked_numbers' => 'array',
            'winning_numbers' => 'array',
            'match_count' => 'integer',
            'multiplier' => 'float',
            'reward_coins' => 'integer',
            'is_win' => 'boolean',
            'is_jackpot' => 'boolean',
            'audit' => 'array',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(LuckyNumberTicket::class, 'ticket_id');
    }

    public function draw(): BelongsTo
    {
        return $this->belongsTo(LuckyNumberDraw::class, 'draw_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(LuckyNumberConfig::class, 'config_id');
    }
}
