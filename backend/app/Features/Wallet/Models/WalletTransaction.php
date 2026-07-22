<?php

namespace App\Features\Wallet\Models;

use App\Features\Users\Models\User;
use Database\Factories\WalletTransactionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletTransaction extends Model
{
    /** @use HasFactory<WalletTransactionFactory> */
    use HasFactory, HasUuids;

    public const TYPE_COIN_PURCHASE = 'COIN_PURCHASE';

    public const TYPE_GIFT_SENT = 'GIFT_SENT';

    public const TYPE_GIFT_RECEIVED = 'GIFT_RECEIVED';

    public const TYPE_PK_REWARD = 'PK_REWARD';

    public const TYPE_WITHDRAW = 'WITHDRAW';

    public const TYPE_ADMIN_ADJUSTMENT = 'ADMIN_ADJUSTMENT';

    public const CURRENCY_COINS = 'coins';

    public const CURRENCY_DIAMONDS = 'diamonds';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'type', 'amount', 'currency', 'reference_type', 'reference_id', 'metadata', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }
}
