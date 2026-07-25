<?php

namespace App\Features\Games\Plugins\Ludo\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LudoPiece extends Model
{
    use HasUuids;

    protected $table = 'ludo_pieces';

    protected $guarded = [];

    protected function casts(): array
    {
        return [

        ];
    }
}
