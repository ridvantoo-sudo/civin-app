<?php

namespace App\Features\Games\Plugins\Ludo\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LudoPlayer extends Model
{
    use HasUuids;

    protected $table = 'ludo_players';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array', 'is_ready' => 'boolean', 'is_host' => 'boolean', 'is_bot' => 'boolean', 'is_spectator' => 'boolean', 'is_connected' => 'boolean', 'disconnected_at' => 'datetime', 'left_at' => 'datetime',
        ];
    }
}
