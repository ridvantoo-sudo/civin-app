<?php

namespace App\Features\SocialEngagement\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAchievement extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'achievement_id',
        'progress',
        'unlocked_at',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'progress' => 'integer',
            'unlocked_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }

    public function isUnlocked(): bool
    {
        return $this->unlocked_at !== null;
    }

    public function isClaimed(): bool
    {
        return $this->claimed_at !== null;
    }
}
