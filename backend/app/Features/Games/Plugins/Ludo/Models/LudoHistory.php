<?php

namespace App\Features\Games\Plugins\Ludo\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LudoHistory extends Model
{
    use HasUuids;

    protected $table = 'ludo_histories';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array', 'is_winner' => 'boolean',
        ];
    }
}
