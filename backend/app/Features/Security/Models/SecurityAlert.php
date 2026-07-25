<?php

namespace App\Features\Security\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityAlert extends Model
{
    use HasUuids;

    public const STATUS_OPEN = 'open';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_DISMISSED = 'dismissed';

    protected $table = 'security_alerts';

    protected $fillable = [
        'user_id',
        'event_id',
        'rule_key',
        'signal',
        'title',
        'severity',
        'surface',
        'action',
        'status',
        'confidence',
        'risk_delta',
        'device_hash',
        'ip_hash',
        'evidence',
        'metadata',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'risk_delta' => 'float',
            'evidence' => 'array',
            'metadata' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(SecurityEvent::class, 'event_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_ACKNOWLEDGED]);
    }
}
