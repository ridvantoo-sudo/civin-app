<?php

namespace App\Features\Global\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GeoComplianceRule extends Model
{
    use HasUuids;

    protected $fillable = [
        'country_code',
        'region_code',
        'feature_key',
        'action',
        'min_age',
        'priority',
        'active',
        'reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'min_age' => 'integer',
            'priority' => 'integer',
            'active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
