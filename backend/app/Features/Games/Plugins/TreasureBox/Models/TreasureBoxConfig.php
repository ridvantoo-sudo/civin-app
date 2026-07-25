<?php

namespace App\Features\Games\Plugins\TreasureBox\Models;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\TreasureBox;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TreasureBoxConfig extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'treasure_box_configs';

    protected $fillable = [
        'game_id',
        'slug',
        'name',
        'description',
        'version',
        'min_coins_amount',
        'max_coins_amount',
        'min_diamonds_amount',
        'max_diamonds_amount',
        'max_winners',
        'min_countdown_seconds',
        'max_countdown_seconds',
        'default_countdown_seconds',
        'min_claim_duration_seconds',
        'max_claim_duration_seconds',
        'default_claim_duration_seconds',
        'max_created_per_day',
        'refund_unclaimed',
        'allowed_reward_types',
        'allowed_types',
        'allowed_visibilities',
        'allowed_eligibilities',
        'allowed_room_types',
        'auto_rules',
        'enabled',
        'status',
        'is_default',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'min_coins_amount' => 'integer',
            'max_coins_amount' => 'integer',
            'min_diamonds_amount' => 'integer',
            'max_diamonds_amount' => 'integer',
            'max_winners' => 'integer',
            'min_countdown_seconds' => 'integer',
            'max_countdown_seconds' => 'integer',
            'default_countdown_seconds' => 'integer',
            'min_claim_duration_seconds' => 'integer',
            'max_claim_duration_seconds' => 'integer',
            'default_claim_duration_seconds' => 'integer',
            'max_created_per_day' => 'integer',
            'refund_unclaimed' => 'boolean',
            'allowed_reward_types' => 'array',
            'allowed_types' => 'array',
            'allowed_visibilities' => 'array',
            'allowed_eligibilities' => 'array',
            'allowed_room_types' => 'array',
            'auto_rules' => 'array',
            'enabled' => 'boolean',
            'is_default' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function treasures(): HasMany
    {
        return $this->hasMany(TreasureBox::class, 'config_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->enabled;
    }

    public function rewardTypeAllowed(string $rewardType): bool
    {
        return in_array($rewardType, $this->allowed_reward_types ?? TreasureBox::REWARD_TYPES, true);
    }

    public function typeAllowed(string $type): bool
    {
        return in_array($type, $this->allowed_types ?? TreasureBox::TYPES, true);
    }

    public function visibilityAllowed(string $visibility): bool
    {
        return in_array($visibility, $this->allowed_visibilities ?? TreasureBox::VISIBILITIES, true);
    }

    public function eligibilityAllowed(string $eligibility): bool
    {
        return in_array($eligibility, $this->allowed_eligibilities ?? TreasureBox::ELIGIBILITY_SCOPES, true);
    }

    public function roomTypeAllowed(string $roomType): bool
    {
        return in_array($roomType, $this->allowed_room_types ?? TreasureBox::ROOM_TYPES, true);
    }

    public function minAmountFor(string $rewardType): int
    {
        return $rewardType === TreasureBox::REWARD_DIAMONDS
            ? (int) $this->min_diamonds_amount
            : (int) $this->min_coins_amount;
    }

    public function maxAmountFor(string $rewardType): int
    {
        return $rewardType === TreasureBox::REWARD_DIAMONDS
            ? (int) $this->max_diamonds_amount
            : (int) $this->max_coins_amount;
    }
}
