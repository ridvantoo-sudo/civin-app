<?php

namespace App\Features\Ads\Models;

use App\Features\Users\Models\User;
use Database\Factories\AdCampaignFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdCampaign extends Model
{
    /** @use HasFactory<AdCampaignFactory> */
    use HasFactory, HasUuids;

    public const PLACEMENT_FEED_POST = 'feed_post';

    public const PLACEMENT_LIVE_BANNER = 'live_banner';

    public const PLACEMENT_LIVE_INTERSTITIAL = 'live_interstitial';

    public const PLACEMENT_LIVE_REWARDED = 'live_rewarded';

    public const PLACEMENT_CHAT_BANNER = 'chat_banner';

    public const PLACEMENT_CHAT_ANNOUNCEMENT = 'chat_announcement';

    public const MEDIA_IMAGE = 'image';

    public const MEDIA_VIDEO = 'video';

    public const TARGET_ALL = 'all';

    public const TARGET_COUNTRY = 'country';

    public const TARGET_LEVEL_RANGE = 'level_range';

    public const TARGET_LIVE_ROOM = 'live_room';

    public const TARGET_VOICE_ROOM = 'voice_room';

    public const TARGET_FAMILY = 'family';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_ENDED = 'ended';

    public const STATUS_REJECTED = 'rejected';

    /** @return list<string> */
    public static function placements(): array
    {
        return [
            self::PLACEMENT_FEED_POST,
            self::PLACEMENT_LIVE_BANNER,
            self::PLACEMENT_LIVE_INTERSTITIAL,
            self::PLACEMENT_LIVE_REWARDED,
            self::PLACEMENT_CHAT_BANNER,
            self::PLACEMENT_CHAT_ANNOUNCEMENT,
        ];
    }

    /** @return list<string> */
    public static function mediaTypes(): array
    {
        return [self::MEDIA_IMAGE, self::MEDIA_VIDEO];
    }

    /** @return list<string> */
    public static function targetTypes(): array
    {
        return [
            self::TARGET_ALL,
            self::TARGET_COUNTRY,
            self::TARGET_LEVEL_RANGE,
            self::TARGET_LIVE_ROOM,
            self::TARGET_VOICE_ROOM,
            self::TARGET_FAMILY,
        ];
    }

    /** @return list<string> */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PENDING_REVIEW,
            self::STATUS_ACTIVE,
            self::STATUS_PAUSED,
            self::STATUS_ENDED,
            self::STATUS_REJECTED,
        ];
    }

    protected $fillable = [
        'owner_user_id',
        'is_admin_campaign',
        'title',
        'body',
        'placement',
        'media_url',
        'media_type',
        'cta_label',
        'cta_url',
        'target_type',
        'target_payload',
        'status',
        'priority',
        'budget_impressions',
        'budget_clicks',
        'impressions_count',
        'clicks_count',
        'starts_at',
        'ends_at',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_admin_campaign' => 'boolean',
            'target_payload' => 'array',
            'priority' => 'integer',
            'budget_impressions' => 'integer',
            'budget_clicks' => 'integer',
            'impressions_count' => 'integer',
            'clicks_count' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AdEvent::class, 'ad_campaign_id');
    }

    public function isRunnableNow(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        $now = now();

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return false;
        }

        if ($this->budget_impressions !== null && $this->impressions_count >= $this->budget_impressions) {
            return false;
        }

        if ($this->budget_clicks !== null && $this->clicks_count >= $this->budget_clicks) {
            return false;
        }

        return true;
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner_user_id !== null && $this->owner_user_id === $user->getKey();
    }

    public function clickThroughRate(): float
    {
        if ($this->impressions_count < 1) {
            return 0.0;
        }

        return round(($this->clicks_count / $this->impressions_count) * 100, 2);
    }
}
