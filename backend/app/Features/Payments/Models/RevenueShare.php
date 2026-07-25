<?php

namespace App\Features\Payments\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevenueShare extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'payment_transaction_id',
        'beneficiary_type',
        'beneficiary_id',
        'currency',
        'amount',
        'rate_bps',
        'status',
        'settled_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'rate_bps' => 'integer',
            'settled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiary_id');
    }
}
