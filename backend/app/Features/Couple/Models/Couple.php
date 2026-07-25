<?php

namespace App\Features\Couple\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Couple extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    public const BADGE_BRONZE = 'bronze';

    public const BADGE_SILVER = 'silver';

    public const BADGE_GOLD = 'gold';

    public const BADGE_PLATINUM = 'platinum';

    public const BADGE_DIAMOND = 'diamond';

    protected $fillable = [
        'user_one_id',
        'user_two_id',
        'proposer_id',
        'display_name',
        'cover_url',
        'badge_key',
        'badge_url',
        'level',
        'xp',
        'xp_to_next_level',
        'status',
        'anniversary_at',
        'last_anniversary_bonus_year',
        'ranking_score',
        'total_gifts_sent',
        'ended_by',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'xp' => 'integer',
            'xp_to_next_level' => 'integer',
            'last_anniversary_bonus_year' => 'integer',
            'ranking_score' => 'integer',
            'total_gifts_sent' => 'integer',
            'anniversary_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposer_id');
    }

    public function endedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(CoupleProposal::class);
    }

    public function gifts(): HasMany
    {
        return $this->hasMany(CoupleGift::class);
    }

    public function taskProgress(): HasMany
    {
        return $this->hasMany(CoupleTaskProgress::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function includesUser(User|string $user): bool
    {
        $userId = $user instanceof User ? $user->getKey() : $user;

        return $this->user_one_id === $userId || $this->user_two_id === $userId;
    }

    public function partnerOf(User|string $user): ?string
    {
        $userId = $user instanceof User ? $user->getKey() : $user;

        if ($this->user_one_id === $userId) {
            return $this->user_two_id;
        }

        if ($this->user_two_id === $userId) {
            return $this->user_one_id;
        }

        return null;
    }

    public static function orderedPair(string $a, string $b): array
    {
        return $a < $b ? [$a, $b] : [$b, $a];
    }

    public static function xpRequiredForLevel(int $level): int
    {
        return (int) max(100, (int) round(100 * pow(max(1, $level), 1.45)));
    }

    public static function badgeKeyForLevel(int $level): string
    {
        return match (true) {
            $level >= 40 => self::BADGE_DIAMOND,
            $level >= 25 => self::BADGE_PLATINUM,
            $level >= 15 => self::BADGE_GOLD,
            $level >= 8 => self::BADGE_SILVER,
            default => self::BADGE_BRONZE,
        };
    }
}
