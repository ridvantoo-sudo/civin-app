<?php

namespace App\Features\Payments\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RevenueShareRule extends Model
{
    use HasUuids;

    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_SELLER = 'seller';

    public const SCOPE_CATEGORY = 'category';

    protected $fillable = [
        'scope',
        'scope_id',
        'platform_bps',
        'agency_bps',
        'active',
        'priority',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'platform_bps' => 'integer',
            'agency_bps' => 'integer',
            'active' => 'boolean',
            'priority' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function merchantBps(): int
    {
        return max(0, 10000 - (int) $this->platform_bps - (int) $this->agency_bps);
    }
}
