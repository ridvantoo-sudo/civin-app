<?php

namespace App\Features\Growth\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferralCode extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'code',
        'uses_count',
        'max_uses',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'uses_count' => 'integer',
            'max_uses' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attributions(): HasMany
    {
        return $this->hasMany(ReferralAttribution::class);
    }

    public function hasRemainingUses(): bool
    {
        if ($this->max_uses === null) {
            return true;
        }

        return $this->uses_count < $this->max_uses;
    }
}
