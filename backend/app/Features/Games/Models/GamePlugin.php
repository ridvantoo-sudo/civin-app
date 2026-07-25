<?php

namespace App\Features\Games\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GamePlugin extends Model
{
    use HasUuids;

    protected $fillable = [
        'slug',
        'name',
        'version',
        'enabled',
        'config',
        'reward_config',
        'coin_limit_per_day',
        'coin_limit_per_play',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'config' => 'array',
            'reward_config' => 'array',
            'coin_limit_per_day' => 'integer',
            'coin_limit_per_play' => 'integer',
        ];
    }
}
