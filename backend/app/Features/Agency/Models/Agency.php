<?php

namespace App\Features\Agency\Models;

use App\Features\Users\Models\User;
use Database\Factories\AgencyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agency extends Model
{
    /** @use HasFactory<AgencyFactory> */
    use HasFactory, HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'description',
        'logo',
        'commission_rate',
        'status',
        'members_count',
        'hosts_count',
        'total_gross_earnings',
        'total_commission',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'members_count' => 'integer',
            'hosts_count' => 'integer',
            'total_gross_earnings' => 'integer',
            'total_commission' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(AgencyMember::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AgencyCommission::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isOwnedBy(User|string $user): bool
    {
        $userId = $user instanceof User ? $user->getKey() : $user;

        return $this->owner_id === $userId;
    }
}
