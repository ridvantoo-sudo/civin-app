<?php

namespace App\Features\Payments\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasUuids;

    public const STATUS_ISSUED = 'issued';

    public const STATUS_PAID = 'paid';

    public const STATUS_VOID = 'void';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'invoice_number',
        'user_id',
        'seller_id',
        'payment_transaction_id',
        'status',
        'currency',
        'subtotal_amount',
        'tax_amount',
        'total_amount',
        'tax_country',
        'billing_name',
        'issued_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_amount' => 'integer',
            'tax_amount' => 'integer',
            'total_amount' => 'integer',
            'issued_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }
}
