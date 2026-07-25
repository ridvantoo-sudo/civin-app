<?php

namespace App\Features\Security\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historical device fingerprints. Stores hashes only — never a raw device id.
 */
class SecurityDevice extends Model
{
    use HasUuids;

    protected $table = 'security_devices';

    protected $fillable = [
        'user_id',
        'device_hash',
        'hardware_hash',
        'os_hash',
        'app_hash',
        'platform',
        'os_version',
        'app_version',
        'model',
        'is_emulator',
        'is_rooted',
        'is_jailbroken',
        'is_debug_build',
        'is_tampered',
        'is_cloned',
        'is_trusted',
        'is_blocked',
        'risk_score',
        'linked_account_count',
        'seen_count',
        'fingerprint',
        'history',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_emulator' => 'boolean',
            'is_rooted' => 'boolean',
            'is_jailbroken' => 'boolean',
            'is_debug_build' => 'boolean',
            'is_tampered' => 'boolean',
            'is_cloned' => 'boolean',
            'is_trusted' => 'boolean',
            'is_blocked' => 'boolean',
            'risk_score' => 'float',
            'linked_account_count' => 'integer',
            'seen_count' => 'integer',
            'fingerprint' => 'array',
            'history' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCompromised(): bool
    {
        return $this->is_rooted || $this->is_jailbroken || $this->is_tampered || $this->is_debug_build;
    }
}
