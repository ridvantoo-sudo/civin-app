<?php

namespace App\Features\Growth\Models;

use App\Features\Growth\Support\PromoRewardType;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCode extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'reward_type',
        'reward_value',
        'max_redemptions',
        'redemptions_count',
        'per_user_limit',
        'active',
        'starts_at',
        'ends_at',
        'created_by',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'reward_value' => 'integer',
            'max_redemptions' => 'integer',
            'redemptions_count' => 'integer',
            'per_user_limit' => 'integer',
            'active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromoRedemption::class);
    }

    public function isCurrentlyValid(): bool
    {
        if (! $this->active) {
            return false;
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return false;
        }

        if ($this->max_redemptions !== null && $this->redemptions_count >= $this->max_redemptions) {
            return false;
        }

        return true;
    }

    public function awardsWalletCredit(): bool
    {
        return in_array($this->reward_type, [PromoRewardType::COINS, PromoRewardType::DIAMONDS], true);
    }
}
