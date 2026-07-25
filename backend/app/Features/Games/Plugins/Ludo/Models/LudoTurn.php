<?php

namespace App\Features\Games\Plugins\Ludo\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LudoTurn extends Model
{
    use HasUuids;

    protected $table = 'ludo_turns';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array', 'started_at' => 'datetime', 'ends_at' => 'datetime', 'completed_at' => 'datetime',
        ];
    }
}
