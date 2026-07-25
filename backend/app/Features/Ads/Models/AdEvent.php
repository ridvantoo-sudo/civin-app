<?php

namespace App\Features\Ads\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdEvent extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const TYPE_IMPRESSION = 'impression';

    public const TYPE_CLICK = 'click';

    public const TYPE_DISMISS = 'dismiss';

    public const TYPE_REWARD_COMPLETE = 'reward_complete';

    /** @return list<string> */
    public static function types(): array
    {
        return [
            self::TYPE_IMPRESSION,
            self::TYPE_CLICK,
            self::TYPE_DISMISS,
            self::TYPE_REWARD_COMPLETE,
        ];
    }

    protected $fillable = [
        'ad_campaign_id',
        'user_id',
        'event_type',
        'placement',
        'context_id',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
