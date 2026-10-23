<?php

namespace App\Features\Vip\Models;

use App\Features\Users\Models\User;
use Database\Factories\UserVipFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserVip extends Model
{
    /** @use HasFactory<UserVipFactory> */
    use HasFactory, HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id',
        'vip_level_id',
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
        return $this->belongsTo(VipLevel::class, 'vip_level_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(VipTransaction::class, 'user_vip_id');
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
