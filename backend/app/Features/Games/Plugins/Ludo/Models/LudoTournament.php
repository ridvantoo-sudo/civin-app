<?php

namespace App\Features\Games\Plugins\Ludo\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LudoTournament extends Model
{
    use HasUuids;

    protected $table = 'ludo_tournaments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'prize_structure' => 'array', 'participant_ids' => 'array', 'metadata' => 'array', 'is_daily' => 'boolean', 'is_scheduled' => 'boolean', 'registration_opens_at' => 'datetime', 'registration_closes_at' => 'datetime', 'scheduled_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime',
        ];
    }
}
