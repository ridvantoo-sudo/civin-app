<?php

namespace App\Features\Security\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A scoped restriction issued by the engine or an administrator.
 *
 * Scopes are intentionally narrow (`wallet`, `games`, `live`) so the platform
 * can lock a single surface without banning the whole account.
 */
class SecurityBan extends Model
{
    use HasUuids;

    public const SCOPE_PLATFORM = 'platform';

    public const SCOPE_WALLET = 'wallet';

    public const SCOPE_GAMES = 'games';

    public const SCOPE_LIVE = 'live';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_LIFTED = 'lifted';

    protected $table = 'security_bans';

    protected $fillable = [
        'user_id',
        'scope',
        'action',
        'reason',
        'rule_key',
        'status',
        'is_automatic',
        'device_hash',
        'ip_hash',
        'evidence',
        'issued_by',
        'lifted_by',
        'starts_at',
        'expires_at',
        'lifted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_automatic' => 'boolean',
            'evidence' => 'array',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'lifted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function scopeEffective(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(function (Builder $inner): void {
                $inner->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    /** @return list<string> */
    public static function scopes(): array
    {
        return [self::SCOPE_PLATFORM, self::SCOPE_WALLET, self::SCOPE_GAMES, self::SCOPE_LIVE];
    }
}
