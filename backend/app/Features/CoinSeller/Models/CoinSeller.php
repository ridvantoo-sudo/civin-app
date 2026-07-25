<?php

namespace App\Features\CoinSeller\Models;

use App\Features\Users\Models\User;
use Database\Factories\CoinSellerFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoinSeller extends Model
{
    /** @use HasFactory<CoinSellerFactory> */
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'user_id',
        'display_name',
        'contact_info',
        'application_message',
        'status',
        'commission_rate',
        'inventory_balance',
        'total_sold',
        'total_commission',
        'sales_count',
        'reviewed_by',
        'admin_notes',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'inventory_balance' => 'integer',
            'total_sold' => 'integer',
            'total_commission' => 'integer',
            'sales_count' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(CoinSellerInventoryLog::class);
    }

    public function rechargeCodes(): HasMany
    {
        return $this->hasMany(CoinRechargeCode::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(CoinSellerSale::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(CoinSellerCommission::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(CoinSellerAuditLog::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isOwnedBy(User|string $user): bool
    {
        $userId = $user instanceof User ? $user->getKey() : $user;

        return $this->user_id === $userId;
    }
}
