<?php

namespace App\Features\Agency\Models;

use App\Features\Users\Models\User;
use Database\Factories\AgencyMemberFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgencyMember extends Model
{
    /** @use HasFactory<AgencyMemberFactory> */
    use HasFactory, HasUuids;

    public const ROLE_OWNER = 'owner';

    public const ROLE_HOST = 'host';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REMOVED = 'removed';

    protected $fillable = [
        'agency_id',
        'user_id',
        'role',
        'status',
        'message',
        'applied_at',
        'reviewed_at',
        'reviewed_by',
        'removed_at',
        'gross_earnings',
        'commission_paid',
    ];

    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'removed_at' => 'datetime',
            'gross_earnings' => 'integer',
            'commission_paid' => 'integer',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AgencyCommission::class, 'agency_member_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isHost(): bool
    {
        return $this->role === self::ROLE_HOST;
    }

    public function isOwnerRole(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }
}
