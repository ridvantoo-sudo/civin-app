<?php

namespace App\Features\Games\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreasureBoxParticipant extends Model
{
    use HasUuids;

    protected $fillable = [
        'treasure_box_id',
        'user_id',
        'joined_at',
        'idempotency_key',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function treasureBox(): BelongsTo
    {
        return $this->belongsTo(TreasureBox::class, 'treasure_box_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
