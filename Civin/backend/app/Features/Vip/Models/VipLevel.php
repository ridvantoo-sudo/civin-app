<?php

namespace App\Features\Vip\Models;

use Database\Factories\VipLevelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VipLevel extends Model
{
    /** @use HasFactory<VipLevelFactory> */
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
        'profile_frame',
        'chat_effect',
        'entrance_animation',
        'exclusive_gifts',
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
            'sort_order' => 'integer',
        ];
    }

    public function userVips(): HasMany
    {
        return $this->hasMany(UserVip::class, 'vip_level_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(VipTransaction::class, 'vip_level_id');
    }

    /** @return array{badge: ?string, profile_frame: ?string, chat_effect: ?string, entrance_animation: ?string, exclusive_gifts: bool} */
    public function privileges(): array
    {
        return [
            'badge' => $this->badge,
            'profile_frame' => $this->profile_frame,
            'chat_effect' => $this->chat_effect,
            'entrance_animation' => $this->entrance_animation,
            'exclusive_gifts' => (bool) $this->exclusive_gifts,
        ];
    }
}
