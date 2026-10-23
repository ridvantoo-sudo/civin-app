<?php

namespace App\Features\Wallet\Models;

use App\Features\Users\Models\User;
use Database\Factories\WithdrawRequestFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawRequest extends Model
{
    /** @use HasFactory<WithdrawRequestFactory> */
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'diamonds', 'amount', 'status', 'approved_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'diamonds' => 'integer',
            'amount' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
