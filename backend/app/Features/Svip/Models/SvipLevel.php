<?php

namespace App\Features\Svip\Models;

use Database\Factories\SvipLevelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SvipLevel extends Model
{
    /** @use HasFactory<SvipLevelFactory> */
    use HasFactory, HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'level',
        'coin_price',
        'duration_days',
        'badge',
        'animated_profile',
        'profile_frame',
        'chat_effect',
        'entrance_animation',
        'exclusive_gifts',
        'exclusive_frames',
        'special_privileges',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'coin_price' => 'integer',
            'duration_days' => 'integer',
            'exclusive_gifts' => 'boolean',
            'exclusive_frames' => 'boolean',
            'special_privileges' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function userSvips(): HasMany
    {
        return $this->hasMany(UserSvip::class, 'svip_level_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SvipTransaction::class, 'svip_level_id');
    }

    /**
     * @return array{
     *     badge: ?string,
     *     animated_profile: ?string,
     *     profile_frame: ?string,
     *     chat_effect: ?string,
     *     entrance_animation: ?string,
     *     exclusive_gifts: bool,
     *     exclusive_frames: bool,
     *     special_privileges: bool
     * }
     */
    public function privileges(): array
    {
        return [
            'badge' => $this->badge,
            'animated_profile' => $this->animated_profile,
            'profile_frame' => $this->profile_frame,
            'chat_effect' => $this->chat_effect,
            'entrance_animation' => $this->entrance_animation,
            'exclusive_gifts' => (bool) $this->exclusive_gifts,
            'exclusive_frames' => (bool) $this->exclusive_frames,
            'special_privileges' => (bool) $this->special_privileges,
        ];
    }
}
