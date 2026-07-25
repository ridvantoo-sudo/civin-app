<?php

namespace App\Features\Challenges\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserChallengeProgress extends Model
{
    use HasUuids;

    protected $table = 'user_challenge_progress';

    protected $fillable = [
        'user_id',
        'challenge_id',
        'period_key',
        'progress',
        'xp_awarded',
        'coins_awarded',
        'diamonds_awarded',
        'completed_at',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'progress' => 'integer',
            'xp_awarded' => 'integer',
            'coins_awarded' => 'integer',
            'diamonds_awarded' => 'integer',
            'completed_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class, 'challenge_id');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isClaimed(): bool
    {
        return $this->claimed_at !== null;
    }
}
