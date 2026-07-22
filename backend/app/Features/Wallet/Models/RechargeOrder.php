<?php

namespace App\Features\Wallet\Models;

use App\Features\Users\Models\User;
use Database\Factories\RechargeOrderFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RechargeOrder extends Model
{
    /** @use HasFactory<RechargeOrderFactory> */
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'package_name', 'coins', 'price', 'currency', 'status',
        'payment_provider', 'transaction_id', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'coins' => 'integer',
            'price' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
