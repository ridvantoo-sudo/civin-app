<?php

namespace App\Features\Games\Plugins\Ludo\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LudoReward extends Model
{
    use HasUuids;

    protected $table = 'ludo_rewards';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array', 'is_refund' => 'boolean',
        ];
    }
}
