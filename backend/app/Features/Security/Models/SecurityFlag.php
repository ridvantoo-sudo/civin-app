<?php

namespace App\Features\Security\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sticky marker on an account. Flags accumulate hit counts so repeat
 * offenders escalate without any single event being decisive.
 */
class SecurityFlag extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLEARED = 'cleared';

    protected $table = 'security_flags';

    protected $fillable = [
        'user_id',
        'flag',
        'severity',
        'surface',
        'status',
        'confidence',
        'hit_count',
        'evidence',
        'first_flagged_at',
        'last_flagged_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'hit_count' => 'integer',
            'evidence' => 'array',
            'first_flagged_at' => 'datetime',
            'last_flagged_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(function (Builder $inner): void {
                $inner->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }
}
