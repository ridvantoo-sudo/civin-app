<?php

namespace App\Features\Games\Models;

use App\Features\Games\Plugins\RedEnvelope\Models\RedEnvelopeConfig;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RedEnvelope extends Model
{
    use HasUuids;

    public const STATUS_OPEN = 'open';

    /** Fully claimed. Kept as the persisted value for backwards compatibility. */
    public const STATUS_EMPTY = 'empty';

    public const STATUS_EXPIRED = 'expired';

    /** Semantic alias of {@see self::STATUS_EMPTY} for plugin consumers. */
    public const STATUS_FINISHED = 'finished';

    public const REWARD_COINS = 'coins';

    public const REWARD_DIAMONDS = 'diamonds';

    public const DIST_RANDOM = 'random';

    public const DIST_FIXED = 'fixed';

    public const VIS_PUBLIC = 'public';

    public const VIS_PRIVATE = 'private';

    public const VIS_LIVE_ROOM = 'live_room';

    public const VIS_VOICE_ROOM = 'voice_room';

    public const SCOPE_EVERYONE = 'everyone';

    public const SCOPE_FOLLOWERS = 'followers';

    public const SCOPE_VIP = 'vip';

    public const SCOPE_FAMILY = 'family';

    public const SCOPE_AGENCY = 'agency';

    public const SCOPE_WHITELIST = 'whitelist';

    public const ROOM_LIVE = 'live';

    public const ROOM_VOICE = 'voice';

    public const ROOM_CHAT = 'chat';

    public const ROOM_FAMILY = 'family';

    public const REWARD_TYPES = [self::REWARD_COINS, self::REWARD_DIAMONDS];

    public const DISTRIBUTION_TYPES = [self::DIST_RANDOM, self::DIST_FIXED];

    public const VISIBILITIES = [self::VIS_PUBLIC, self::VIS_PRIVATE, self::VIS_LIVE_ROOM, self::VIS_VOICE_ROOM];

    public const CLAIM_SCOPES = [
        self::SCOPE_EVERYONE,
        self::SCOPE_FOLLOWERS,
        self::SCOPE_VIP,
        self::SCOPE_FAMILY,
        self::SCOPE_AGENCY,
        self::SCOPE_WHITELIST,
    ];

    public const ROOM_TYPES = [self::ROOM_LIVE, self::ROOM_VOICE, self::ROOM_CHAT, self::ROOM_FAMILY];

    /** Legacy column => plugin column. Kept in sync on every save. */
    private const MIRRORED_COLUMNS = [
        'sender_id' => 'creator_id',
        'total_slots' => 'winner_count',
        'remaining_slots' => 'remaining_count',
    ];

    protected $fillable = [
        'game_id',
        'sender_id',
        'total_coins',
        'remaining_coins',
        'total_slots',
        'remaining_slots',
        'message',
        'status',
        'expires_at',
        'creator_id',
        'room_id',
        'room_type',
        'reward_type',
        'distribution_type',
        'visibility',
        'claim_scope',
        'whitelist_user_ids',
        'total_amount',
        'remaining_amount',
        'winner_count',
        'remaining_count',
        'idempotency_key',
        'game_play_id',
        'wallet_transaction_id',
        'config_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'total_coins' => 'integer',
            'remaining_coins' => 'integer',
            'total_slots' => 'integer',
            'remaining_slots' => 'integer',
            'expires_at' => 'datetime',
            'whitelist_user_ids' => 'array',
            'total_amount' => 'integer',
            'remaining_amount' => 'integer',
            'winner_count' => 'integer',
            'remaining_count' => 'integer',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $envelope): void {
            $envelope->syncLegacyColumns();
        });
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(RedEnvelopeConfig::class, 'config_id');
    }

    public function play(): BelongsTo
    {
        return $this->belongsTo(GamePlay::class, 'game_play_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(RedEnvelopeClaim::class, 'envelope_id');
    }

    public function ownerId(): ?string
    {
        return $this->creator_id ?? $this->sender_id;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN && ! $this->isExpired() && $this->slotsLeft() > 0;
    }

    public function isExpired(): bool
    {
        if ($this->status === self::STATUS_EXPIRED) {
            return true;
        }

        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_EMPTY, self::STATUS_FINISHED], true);
    }

    public function slotsLeft(): int
    {
        return (int) ($this->remaining_count ?? $this->remaining_slots ?? 0);
    }

    public function amountLeft(): int
    {
        return (int) ($this->remaining_amount ?? $this->remaining_coins ?? 0);
    }

    public function rewardType(): string
    {
        return $this->reward_type ?? self::REWARD_COINS;
    }

    public function paysCoins(): bool
    {
        return $this->rewardType() === self::REWARD_COINS;
    }

    /**
     * Keeps the legacy schema (sender_id / total_coins / total_slots) and the
     * plugin schema (creator_id / total_amount / winner_count) mirrored so both
     * the legacy `/games/play` flow and the plugin flow observe the same state.
     */
    public function syncLegacyColumns(): void
    {
        foreach (self::MIRRORED_COLUMNS as $legacy => $modern) {
            $this->mirrorPair($legacy, $modern);
        }

        if ($this->paysCoins()) {
            $this->mirrorPair('total_coins', 'total_amount');
            $this->mirrorPair('remaining_coins', 'remaining_amount');
        } else {
            // The legacy `/games/play` flow only ever pays coins, so diamond
            // envelopes keep their legacy columns zeroed. That makes the legacy
            // claim path treat them as closed instead of paying out 1 coin.
            $this->total_coins = 0;
            $this->remaining_coins = 0;
            $this->total_slots = 0;
            $this->remaining_slots = 0;
        }

        $this->total_coins ??= 0;
        $this->remaining_coins ??= 0;
        $this->total_slots ??= 1;
        $this->remaining_slots ??= 0;
    }

    private function mirrorPair(string $legacy, string $modern): void
    {
        $legacyValue = $this->getAttribute($legacy);
        $modernValue = $this->getAttribute($modern);

        $legacyDirty = $this->isDirty($legacy);
        $modernDirty = $this->isDirty($modern);

        if ($legacyDirty && ! $modernDirty) {
            $this->setAttribute($modern, $legacyValue);

            return;
        }

        if ($modernDirty && ! $legacyDirty) {
            $this->setAttribute($legacy, $modernValue);

            return;
        }

        if ($legacyValue === null && $modernValue !== null) {
            $this->setAttribute($legacy, $modernValue);

            return;
        }

        if ($modernValue === null && $legacyValue !== null) {
            $this->setAttribute($modern, $legacyValue);
        }
    }
}
