<?php

namespace App\Features\Payments\Models;

use App\Features\Payments\Support\PaymentStatus;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentTransaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'payable_type',
        'payable_id',
        'gateway',
        'status',
        'currency',
        'amount',
        'fee_amount',
        'tax_amount',
        'net_amount',
        'refunded_amount',
        'idempotency_key',
        'provider_reference',
        'provider_payment_id',
        'client_secret',
        'checkout_url',
        'error_code',
        'error_message',
        'authorized_at',
        'paid_at',
        'failed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'fee_amount' => 'integer',
            'tax_amount' => 'integer',
            'net_amount' => 'integer',
            'refunded_amount' => 'integer',
            'authorized_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    public function revenueShares(): HasMany
    {
        return $this->hasMany(RevenueShare::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'id', 'payment_transaction_id');
    }

    public function isSucceeded(): bool
    {
        return $this->status === PaymentStatus::SUCCEEDED;
    }

    public function isRefundable(): bool
    {
        return $this->isSucceeded() && $this->refunded_amount < $this->amount;
    }

    public function refundableAmount(): int
    {
        return max(0, (int) $this->amount - (int) $this->refunded_amount);
    }
}
