<?php

namespace App\Features\Security\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Manual review case. Anything the engine is not confident enough to enforce
 * lands here instead of being auto-actioned.
 */
class SecurityReview extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const RESOLUTION_CLEARED = 'cleared';

    public const RESOLUTION_WARNED = 'warned';

    public const RESOLUTION_SUSPENDED = 'suspended';

    public const RESOLUTION_BANNED = 'banned';

    protected $table = 'security_reviews';

    protected $fillable = [
        'user_id',
        'alert_id',
        'queue',
        'reason',
        'status',
        'priority',
        'resolution',
        'reviewer_notes',
        'risk_score',
        'context',
        'assigned_to',
        'reviewed_by',
        'due_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'risk_score' => 'float',
            'context' => 'array',
            'due_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(SecurityAlert::class, 'alert_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    }
}
