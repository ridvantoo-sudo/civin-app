<?php

namespace App\Features\Global\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CdnConfig extends Model
{
    use HasUuids;

    protected $fillable = [
        'key',
        'base_url',
        'public_base_url',
        'cache_ttl_seconds',
        'image_params',
        'prefixes',
        'edge_map',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'cache_ttl_seconds' => 'integer',
            'image_params' => 'array',
            'prefixes' => 'array',
            'edge_map' => 'array',
            'active' => 'boolean',
        ];
    }
}
