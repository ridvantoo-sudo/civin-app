<?php

namespace App\Features\Games\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreasureBoxReward extends Model
{
    use HasUuids;

    protected $fillable = [
        'treasure_box_id',
        'reward_type',
        'amount',
        'label',
        'metadata',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function treasureBox(): BelongsTo
    {
        return $this->belongsTo(TreasureBox::class, 'treasure_box_id');
    }
}
