<?php

namespace App\Features\Games\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RedEnvelopeClaim extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'envelope_id',
        'user_id',
        'coins',
        'created_at',
        'reward_amount',
        'reward_type',
        'claimed_at',
        'idempotency_key',
        'wallet_transaction_id',
        'play_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'coins' => 'integer',
            'created_at' => 'datetime',
            'reward_amount' => 'integer',
            'claimed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $claim): void {
            $claim->syncLegacyColumns();
        });
    }

    public function envelope(): BelongsTo
    {
        return $this->belongsTo(RedEnvelope::class, 'envelope_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function play(): BelongsTo
    {
        return $this->belongsTo(GamePlay::class, 'play_id');
    }

    public function rewardType(): string
    {
        return $this->reward_type ?? RedEnvelope::REWARD_COINS;
    }

    public function rewardAmount(): int
    {
        return (int) ($this->reward_amount ?? $this->coins ?? 0);
    }

    /**
     * Mirrors the legacy `coins` column and the plugin `reward_amount` column.
     * Diamond payouts keep `coins` at zero so legacy readers never over-report.
     */
    public function syncLegacyColumns(): void
    {
        $paysCoins = $this->rewardType() === RedEnvelope::REWARD_COINS;

        if ($paysCoins) {
            if ($this->isDirty('coins') && ! $this->isDirty('reward_amount')) {
                $this->reward_amount = (int) $this->coins;
            } elseif ($this->isDirty('reward_amount') && ! $this->isDirty('coins')) {
                $this->coins = (int) $this->reward_amount;
            } elseif ($this->coins === null && $this->reward_amount !== null) {
                $this->coins = (int) $this->reward_amount;
            } elseif ($this->reward_amount === null && $this->coins !== null) {
                $this->reward_amount = (int) $this->coins;
            }
        }

        $this->coins ??= 0;
        $this->reward_amount ??= 0;
        $this->reward_type ??= RedEnvelope::REWARD_COINS;
        $this->claimed_at ??= $this->created_at ?? now();
        $this->created_at ??= $this->claimed_at;
    }
}
