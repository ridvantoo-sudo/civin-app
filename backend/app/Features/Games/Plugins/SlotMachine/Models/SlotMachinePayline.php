<?php

namespace App\Features\Games\Plugins\SlotMachine\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlotMachinePayline extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const LAYOUT_CLASSIC = 'classic';

    public const LAYOUT_PREMIUM = 'premium';

    protected $table = 'slot_machine_paylines';

    protected $fillable = [
        'config_id',
        'code',
        'label',
        'layout',
        'positions',
        'sort_order',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'positions' => 'array',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(SlotMachineConfig::class, 'config_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
