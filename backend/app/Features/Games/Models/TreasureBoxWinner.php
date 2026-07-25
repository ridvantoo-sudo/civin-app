<?php

namespace App\Features\Games\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreasureBoxWinner extends Model
{
    use HasUuids;

    protected $fillable = [
        'treasure_box_id',
        'user_id',
        'participant_id',
        'reward_type',
        'reward_amount',
        'is_jackpot',
        'wallet_transaction_id',
        'play_id',
        'rewarded_at',
        'idempotency_key',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'reward_amount' => 'integer',
            'is_jackpot' => 'boolean',
            'rewarded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function treasureBox(): BelongsTo
    {
        return $this->belongsTo(TreasureBox::class, 'treasure_box_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(TreasureBoxParticipant::class, 'participant_id');
    }

    public function play(): BelongsTo
    {
        return $this->belongsTo(GamePlay::class, 'play_id');
    }

    public function rewardAmount(): int
    {
        return (int) $this->reward_amount;
    }

    public function rewardType(): string
    {
        return $this->reward_type ?? TreasureBox::REWARD_COINS;
    }
}
