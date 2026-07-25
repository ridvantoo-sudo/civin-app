<?php

namespace App\Features\FamilyClub\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyMissionProgress extends Model
{
    use HasUuids;

    protected $table = 'family_mission_progress';

    protected $fillable = [
        'family_id',
        'mission_id',
        'period_key',
        'progress',
        'completed_at',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'progress' => 'integer',
            'completed_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(FamilyMission::class, 'mission_id');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isClaimed(): bool
    {
        return $this->claimed_at !== null;
    }
}
