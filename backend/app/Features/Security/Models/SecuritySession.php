<?php

namespace App\Features\Security\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecuritySession extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    public const STATUS_REVOKED = 'revoked';

    protected $table = 'security_sessions';

    protected $fillable = [
        'user_id',
        'session_hash',
        'device_hash',
        'ip_hash',
        'country_code',
        'platform',
        'status',
        'is_verified',
        'request_count',
        'anomaly_count',
        'risk_score',
        'metadata',
        'started_at',
        'last_seen_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'request_count' => 'integer',
            'anomaly_count' => 'integer',
            'risk_score' => 'float',
            'metadata' => 'array',
            'started_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
