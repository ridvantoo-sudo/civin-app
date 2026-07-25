<?php

namespace App\Features\Games\Plugins\RedEnvelope\Models;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\RedEnvelope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RedEnvelopeConfig extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'red_envelope_configs';

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
        'min_duration_seconds',
        'max_duration_seconds',
        'default_duration_seconds',
        'max_created_per_day',
        'refund_unclaimed',
        'allowed_reward_types',
        'allowed_distribution_types',
        'allowed_visibilities',
        'allowed_claim_scopes',
        'allowed_room_types',
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
            'min_duration_seconds' => 'integer',
            'max_duration_seconds' => 'integer',
            'default_duration_seconds' => 'integer',
            'max_created_per_day' => 'integer',
            'refund_unclaimed' => 'boolean',
            'allowed_reward_types' => 'array',
            'allowed_distribution_types' => 'array',
            'allowed_visibilities' => 'array',
            'allowed_claim_scopes' => 'array',
            'allowed_room_types' => 'array',
            'enabled' => 'boolean',
            'is_default' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function envelopes(): HasMany
    {
        return $this->hasMany(RedEnvelope::class, 'config_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->enabled;
    }

    public function rewardTypeAllowed(string $rewardType): bool
    {
        return in_array($rewardType, $this->allowed_reward_types ?? RedEnvelope::REWARD_TYPES, true);
    }

    public function distributionTypeAllowed(string $distributionType): bool
    {
        return in_array($distributionType, $this->allowed_distribution_types ?? RedEnvelope::DISTRIBUTION_TYPES, true);
    }

    public function visibilityAllowed(string $visibility): bool
    {
        return in_array($visibility, $this->allowed_visibilities ?? RedEnvelope::VISIBILITIES, true);
    }

    public function claimScopeAllowed(string $claimScope): bool
    {
        return in_array($claimScope, $this->allowed_claim_scopes ?? RedEnvelope::CLAIM_SCOPES, true);
    }

    public function roomTypeAllowed(string $roomType): bool
    {
        return in_array($roomType, $this->allowed_room_types ?? RedEnvelope::ROOM_TYPES, true);
    }

    public function minAmountFor(string $rewardType): int
    {
        return $rewardType === RedEnvelope::REWARD_DIAMONDS
            ? (int) $this->min_diamonds_amount
            : (int) $this->min_coins_amount;
    }

    public function maxAmountFor(string $rewardType): int
    {
        return $rewardType === RedEnvelope::REWARD_DIAMONDS
            ? (int) $this->max_diamonds_amount
            : (int) $this->max_coins_amount;
    }
}
