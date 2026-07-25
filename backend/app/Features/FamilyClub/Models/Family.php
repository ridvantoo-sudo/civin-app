<?php

namespace App\Features\FamilyClub\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Family extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_SUSPENDED = 'suspended';

    public const BADGE_BRONZE = 'bronze';

    public const BADGE_SILVER = 'silver';

    public const BADGE_GOLD = 'gold';

    public const BADGE_PLATINUM = 'platinum';

    public const BADGE_DIAMOND = 'diamond';

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'description',
        'logo',
        'badge_key',
        'badge_url',
        'level',
        'xp',
        'xp_to_next_level',
        'status',
        'members_count',
        'max_members',
        'ranking_score',
        'total_gifts_received',
        'total_gifts_sent',
        'wallet_coins',
        'wallet_diamonds',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'xp' => 'integer',
            'xp_to_next_level' => 'integer',
            'members_count' => 'integer',
            'max_members' => 'integer',
            'ranking_score' => 'integer',
            'total_gifts_received' => 'integer',
            'total_gifts_sent' => 'integer',
            'wallet_coins' => 'integer',
            'wallet_diamonds' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(FamilyInvite::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(FamilyMessage::class);
    }

    public function gifts(): HasMany
    {
        return $this->hasMany(FamilyGift::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(FamilyWalletTransaction::class);
    }

    public function missionProgress(): HasMany
    {
        return $this->hasMany(FamilyMissionProgress::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isOwnedBy(User|string $user): bool
    {
        $userId = $user instanceof User ? $user->getKey() : $user;

        return $this->owner_id === $userId;
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
