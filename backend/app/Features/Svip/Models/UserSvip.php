<?php

namespace App\Features\Svip\Models;

use App\Features\Users\Models\User;
use Database\Factories\UserSvipFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserSvip extends Model
{
    /** @use HasFactory<UserSvipFactory> */
    use HasFactory, HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id',
        'svip_level_id',
        'status',
        'started_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(SvipLevel::class, 'svip_level_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SvipTransaction::class, 'user_svip_id');
    }

    public function isExpired(?\DateTimeInterface $at = null): bool
    {
        $at ??= now();

        return $this->expires_at !== null && $this->expires_at->lte($at);
    }

    public function isActive(?\DateTimeInterface $at = null): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->isExpired($at);
    }
}
