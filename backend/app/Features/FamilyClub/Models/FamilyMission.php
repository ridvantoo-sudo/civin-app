<?php

namespace App\Features\FamilyClub\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FamilyMission extends Model
{
    use HasUuids;

    public const PERIOD_DAILY = 'daily';

    public const PERIOD_WEEKLY = 'weekly';

    protected $fillable = [
        'key',
        'title',
        'description',
        'period',
        'target_value',
        'reward_xp',
        'reward_coins',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'target_value' => 'integer',
            'reward_xp' => 'integer',
            'reward_coins' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function progressRows(): HasMany
    {
        return $this->hasMany(FamilyMissionProgress::class, 'mission_id');
    }
}
