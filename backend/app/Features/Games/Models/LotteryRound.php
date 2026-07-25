<?php

namespace App\Features\Games\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LotteryRound extends Model
{
    use HasUuids;

    public const STATUS_OPEN = 'open';

    public const STATUS_DRAWN = 'drawn';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'game_id',
        'ticket_price',
        'max_number',
        'picks',
        'winning_numbers',
        'status',
        'closes_at',
        'drawn_at',
    ];

    protected function casts(): array
    {
        return [
            'ticket_price' => 'integer',
            'max_number' => 'integer',
            'picks' => 'integer',
            'winning_numbers' => 'array',
            'closes_at' => 'datetime',
            'drawn_at' => 'datetime',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(LotteryTicket::class, 'round_id');
    }
}
