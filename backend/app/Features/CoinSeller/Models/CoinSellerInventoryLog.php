<?php

namespace App\Features\CoinSeller\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CoinSellerInventoryLog extends Model
{
    use HasUuids;

    public const TYPE_ADMIN_ALLOCATE = 'admin_allocate';

    public const TYPE_SALE_DEBIT = 'sale_debit';

    public const TYPE_CODE_RESERVE = 'code_reserve';

    public const TYPE_CODE_REVOKE = 'code_revoke';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public $timestamps = false;

    protected $fillable = [
        'coin_seller_id',
        'actor_id',
        'type',
        'amount',
        'balance_after',
        'reference_type',
        'reference_id',
        'notes',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_after' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(CoinSeller::class, 'coin_seller_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }
}
