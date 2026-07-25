<?php

namespace App\Features\Payments\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    use HasUuids;

    protected $fillable = [
        'country_code',
        'region',
        'name',
        'rate_bps',
        'inclusive',
        'active',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'rate_bps' => 'integer',
            'inclusive' => 'boolean',
            'active' => 'boolean',
            'priority' => 'integer',
        ];
    }
}
