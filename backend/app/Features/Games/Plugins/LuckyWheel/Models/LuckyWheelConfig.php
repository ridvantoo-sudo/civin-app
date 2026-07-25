<?php

namespace App\Features\Games\Plugins\LuckyWheel\Models;

use App\Features\Games\Models\Game;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LuckyWheelConfig extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'lucky_wheel_configs';

    protected $fillable = [
        'game_id',
        'slug',
        'name',
        'description',
        'version',
        'spin_cost_coins',
        'daily_free_spins',
        'max_spins_per_day',
        'cooldown_seconds',
        'jackpot_enabled',
        'jackpot_coins',
        'status',
        'is_default',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'spin_cost_coins' => 'integer',
            'daily_free_spins' => 'integer',
            'max_spins_per_day' => 'integer',
            'cooldown_seconds' => 'integer',
            'jackpot_enabled' => 'boolean',
            'jackpot_coins' => 'integer',
            'is_default' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function sectors(): HasMany
    {
        return $this->hasMany(LuckyWheelSector::class, 'config_id')->orderBy('position');
    }

    public function spins(): HasMany
    {
        return $this->hasMany(LuckyWheelSpin::class, 'config_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
