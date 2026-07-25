<?php

namespace App\Features\Games\Models;

use App\Features\Games\Plugins\TreasureBox\Models\TreasureBoxConfig;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class TreasureBox extends Model
{
    use HasUuids;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_OPEN = 'open';

    public const STATUS_COUNTDOWN = 'countdown';

    public const STATUS_OPENED = 'opened';

    public const STATUS_FINISHED = 'finished';

    public const STATUS_EXPIRED = 'expired';

    public const TYPE_INSTANT = 'instant';

    public const TYPE_SCHEDULED = 'scheduled';

    public const TYPE_AUTO = 'auto';

    public const TYPE_LIVE_ROOM = 'live_room';

    public const TYPE_VOICE_ROOM = 'voice_room';

    public const REWARD_COINS = 'coins';

    public const REWARD_DIAMONDS = 'diamonds';

    public const REWARD_GIFT = 'gift';

    public const REWARD_FRAME = 'frame';

    public const REWARD_ENTRY_EFFECT = 'entry_effect';

    public const REWARD_VIP_DAYS = 'vip_days';

    public const REWARD_SVIP_DAYS = 'svip_days';

    public const REWARD_MYSTERY = 'mystery';

    public const REWARD_JACKPOT = 'jackpot';

    public const DIST_RANDOM = 'random';

    public const DIST_FIXED = 'fixed';

    public const VIS_PUBLIC = 'public';

    public const VIS_PRIVATE = 'private';

    public const VIS_LIVE_ROOM = 'live_room';

    public const VIS_VOICE_ROOM = 'voice_room';

    public const SCOPE_EVERYONE = 'everyone';

    public const SCOPE_FOLLOWERS = 'followers';

    public const SCOPE_VIP = 'vip';

    public const SCOPE_SVIP = 'svip';

    public const SCOPE_FAMILY = 'family';

    public const SCOPE_AGENCY = 'agency';

    public const SCOPE_MIN_WATCH_TIME = 'min_watch_time';

    public const SCOPE_MIN_USER_LEVEL = 'min_user_level';

    public const SCOPE_MIN_GIFT_VALUE = 'min_gift_value';

    public const SCOPE_WHITELIST = 'whitelist';

    public const ROOM_LIVE = 'live';

    public const ROOM_VOICE = 'voice';

    public const ROOM_CHAT = 'chat';

    public const ROOM_FAMILY = 'family';

    public const TYPES = [
        self::TYPE_INSTANT,
        self::TYPE_SCHEDULED,
        self::TYPE_AUTO,
        self::TYPE_LIVE_ROOM,
        self::TYPE_VOICE_ROOM,
    ];

    public const STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_OPEN,
        self::STATUS_COUNTDOWN,
        self::STATUS_OPENED,
        self::STATUS_FINISHED,
        self::STATUS_EXPIRED,
    ];

    public const REWARD_TYPES = [
        self::REWARD_COINS,
        self::REWARD_DIAMONDS,
        self::REWARD_GIFT,
        self::REWARD_FRAME,
        self::REWARD_ENTRY_EFFECT,
        self::REWARD_VIP_DAYS,
        self::REWARD_SVIP_DAYS,
        self::REWARD_MYSTERY,
        self::REWARD_JACKPOT,
    ];

    public const WALLET_REWARD_TYPES = [
        self::REWARD_COINS,
        self::REWARD_DIAMONDS,
    ];

    public const DISTRIBUTION_TYPES = [self::DIST_RANDOM, self::DIST_FIXED];

    public const VISIBILITIES = [self::VIS_PUBLIC, self::VIS_PRIVATE, self::VIS_LIVE_ROOM, self::VIS_VOICE_ROOM];

    public const ELIGIBILITY_SCOPES = [
        self::SCOPE_EVERYONE,
        self::SCOPE_FOLLOWERS,
        self::SCOPE_VIP,
        self::SCOPE_SVIP,
        self::SCOPE_FAMILY,
        self::SCOPE_AGENCY,
        self::SCOPE_MIN_WATCH_TIME,
        self::SCOPE_MIN_USER_LEVEL,
        self::SCOPE_MIN_GIFT_VALUE,
        self::SCOPE_WHITELIST,
    ];

    public const ROOM_TYPES = [self::ROOM_LIVE, self::ROOM_VOICE, self::ROOM_CHAT, self::ROOM_FAMILY];

    protected $fillable = [
        'game_id',
        'config_id',
        'creator_id',
        'type',
        'status',
        'reward_type',
        'total_amount',
        'remaining_amount',
        'winner_count',
        'participant_count',
        'room_id',
        'room_type',
        'visibility',
        'eligibility_scope',
        'whitelist_user_ids',
        'min_watch_time_seconds',
        'min_user_level',
        'min_gift_value',
        'countdown_seconds',
        'claim_duration_seconds',
        'scheduled_at',
        'countdown_started_at',
        'opens_at',
        'draw_at',
        'expires_at',
        'auto_start',
        'message',
        'idempotency_key',
        'game_play_id',
        'wallet_transaction_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'integer',
            'remaining_amount' => 'integer',
            'winner_count' => 'integer',
            'participant_count' => 'integer',
            'whitelist_user_ids' => 'array',
            'min_watch_time_seconds' => 'integer',
            'min_user_level' => 'integer',
            'min_gift_value' => 'integer',
            'countdown_seconds' => 'integer',
            'claim_duration_seconds' => 'integer',
            'scheduled_at' => 'datetime',
            'countdown_started_at' => 'datetime',
            'opens_at' => 'datetime',
            'draw_at' => 'datetime',
            'expires_at' => 'datetime',
            'auto_start' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(TreasureBoxConfig::class, 'config_id');
    }

    public function play(): BelongsTo
    {
        return $this->belongsTo(GamePlay::class, 'game_play_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TreasureBoxParticipant::class, 'treasure_box_id');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(TreasureBoxReward::class, 'treasure_box_id');
    }

    public function winners(): HasMany
    {
        return $this->hasMany(TreasureBoxWinner::class, 'treasure_box_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TreasureBoxHistory::class, 'treasure_box_id');
    }

    public function ownerId(): ?string
    {
        return $this->creator_id;
    }

    public function allowsJoining(): bool
    {
        if (! in_array($this->status, [self::STATUS_OPEN, self::STATUS_COUNTDOWN], true)) {
            return false;
        }

        if ($this->isExpired()) {
            return false;
        }

        if ($this->draw_at !== null && $this->draw_at->isPast()) {
            return false;
        }

        if ($this->opens_at !== null && $this->opens_at->isFuture()) {
            return false;
        }

        if ($this->joinWindowEndsAt() !== null && $this->joinWindowEndsAt()->isPast()) {
            return false;
        }

        return true;
    }

    public function joinWindowEndsAt(): ?Carbon
    {
        if ($this->opens_at === null) {
            return $this->draw_at;
        }

        $byDuration = $this->opens_at->copy()->addSeconds((int) $this->claim_duration_seconds);

        if ($this->draw_at === null) {
            return $byDuration;
        }

        return $byDuration->lt($this->draw_at) ? $byDuration : $this->draw_at;
    }

    public function isExpired(): bool
    {
        if ($this->status === self::STATUS_EXPIRED) {
            return true;
        }

        return $this->expires_at !== null && $this->expires_at->isPast()
            && ! in_array($this->status, [self::STATUS_FINISHED, self::STATUS_OPENED], true);
    }

    public function isFinished(): bool
    {
        return $this->status === self::STATUS_FINISHED;
    }

    public function isOpen(): bool
    {
        return $this->allowsJoining();
    }

    public function amountLeft(): int
    {
        return (int) $this->remaining_amount;
    }

    public function rewardType(): string
    {
        return $this->reward_type ?? self::REWARD_COINS;
    }

    public function paysCoins(): bool
    {
        return $this->rewardType() === self::REWARD_COINS;
    }

    public function paysDiamonds(): bool
    {
        return $this->rewardType() === self::REWARD_DIAMONDS;
    }

    public function isWalletReward(): bool
    {
        return in_array($this->rewardType(), self::WALLET_REWARD_TYPES, true);
    }

    public function distributionType(): string
    {
        return (string) (($this->metadata['distribution_type'] ?? null) ?: self::DIST_RANDOM);
    }
}
