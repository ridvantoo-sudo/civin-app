<?php

namespace App\Features\Games\Plugins\Ludo\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LudoMove extends Model
{
    use HasUuids;

    protected $table = 'ludo_moves';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'captured_piece' => 'array', 'metadata' => 'array', 'granted_extra_turn' => 'boolean',
        ];
    }
}
