<?php

namespace App\Features\CoinSeller\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoinSellerAuditLog extends Model
{
    use HasUuids;

    public const ACTION_APPLIED = 'applied';

    public const ACTION_APPROVED = 'approved';

    public const ACTION_REJECTED = 'rejected';

    public const ACTION_SUSPENDED = 'suspended';

    public const ACTION_REACTIVATED = 'reactivated';

    public const ACTION_INVENTORY_ALLOCATED = 'inventory_allocated';

    public const ACTION_CODE_CREATED = 'code_created';

    public const ACTION_CODE_REVOKED = 'code_revoked';

    public const ACTION_SALE_COMPLETED = 'sale_completed';

    public const ACTION_CODE_REDEEMED = 'code_redeemed';

    public $timestamps = false;

    protected $fillable = [
        'coin_seller_id',
        'actor_id',
        'action',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
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
}
