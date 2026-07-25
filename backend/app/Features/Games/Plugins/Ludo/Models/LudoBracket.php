<?php

namespace App\Features\Games\Plugins\Ludo\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LudoBracket extends Model
{
    use HasUuids;

    protected $table = 'ludo_brackets';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'player_ids' => 'array', 'metadata' => 'array',
        ];
    }
}
