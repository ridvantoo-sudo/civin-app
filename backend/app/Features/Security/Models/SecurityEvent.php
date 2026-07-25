<?php

namespace App\Features\Security\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only, privacy-scrubbed record of a domain event observed by the
 * anti-cheat platform.
 */
class SecurityEvent extends Model
{
    use HasUuids;

    protected $table = 'security_events';

    public $timestamps = false;

    protected $fillable = [
        'idempotency_key',
        'user_id',
        'event',
        'source',
        'category',
        'surface',
        'subject_id',
        'subject_type',
        'device_hash',
        'session_hash',
        'ip_hash',
        'country_code',
        'platform',
        'amount',
        'currency',
        'risk_contribution',
        'analyzed',
        'payload',
        'metadata',
        'occurred_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'metadata' => 'array',
            'amount' => 'decimal:4',
            'risk_contribution' => 'decimal:4',
            'analyzed' => 'boolean',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForSurface(Builder $query, string $surface): Builder
    {
        return $query->where('surface', $surface);
    }

    public function scopeForUser(Builder $query, ?string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
