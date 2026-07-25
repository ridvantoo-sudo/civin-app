<?php

namespace App\Features\Levels\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Badge extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const UNLOCK_LEVEL = 'level';

    public const UNLOCK_XP_TOTAL = 'xp_total';

    public const UNLOCK_GIFTS_SENT = 'gifts_sent';

    public const UNLOCK_GIFTS_RECEIVED = 'gifts_received';

    public const UNLOCK_LIVE_MINUTES = 'live_minutes';

    public const UNLOCK_LOGIN_STREAK = 'login_days';

    protected $fillable = [
        'code',
        'name',
        'description',
        'icon',
        'animation_url',
        'unlock_type',
        'unlock_value',
        'xp_reward',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'unlock_value' => 'integer',
            'xp_reward' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function awards(): HasMany
    {
        return $this->hasMany(UserBadge::class, 'badge_id');
    }
}
