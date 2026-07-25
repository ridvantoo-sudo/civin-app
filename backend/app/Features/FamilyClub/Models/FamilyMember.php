<?php

namespace App\Features\FamilyClub\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyMember extends Model
{
    use HasUuids;

    public const ROLE_OWNER = 'owner';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_MEMBER = 'member';

    public const STATUS_PENDING = 'pending';

    public const STATUS_INVITED = 'invited';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REMOVED = 'removed';

    protected $fillable = [
        'family_id',
        'user_id',
        'role',
        'status',
        'message',
        'invited_at',
        'joined_at',
        'reviewed_at',
        'invited_by',
        'reviewed_by',
        'removed_at',
        'contribution_xp',
        'contribution_gifts',
    ];

    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'joined_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'removed_at' => 'datetime',
            'contribution_xp' => 'integer',
            'contribution_gifts' => 'integer',
        ];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isInvited(): bool
    {
        return $this->status === self::STATUS_INVITED;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isOwnerRole(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isAdminRole(): bool
    {
        return $this->role === self::ROLE_ADMIN || $this->isOwnerRole();
    }

    public static function assignableRoles(): array
    {
        return [self::ROLE_ADMIN, self::ROLE_MEMBER];
    }
}
