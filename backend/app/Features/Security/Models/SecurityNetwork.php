<?php

namespace App\Features\Security\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Network provenance for a user, keyed by hashed IP.
 */
class SecurityNetwork extends Model
{
    use HasUuids;

    protected $table = 'security_networks';

    protected $fillable = [
        'ip_hash',
        'user_id',
        'asn',
        'organization',
        'country_code',
        'region',
        'is_vpn',
        'is_proxy',
        'is_tor',
        'is_hosting',
        'is_blocked',
        'latency_ms',
        'packet_frequency',
        'distinct_users',
        'anomaly_count',
        'risk_score',
        'metadata',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_vpn' => 'boolean',
            'is_proxy' => 'boolean',
            'is_tor' => 'boolean',
            'is_hosting' => 'boolean',
            'is_blocked' => 'boolean',
            'latency_ms' => 'integer',
            'packet_frequency' => 'float',
            'distinct_users' => 'integer',
            'anomaly_count' => 'integer',
            'risk_score' => 'float',
            'metadata' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAnonymized(): bool
    {
        return $this->is_vpn || $this->is_proxy || $this->is_tor;
    }
}
