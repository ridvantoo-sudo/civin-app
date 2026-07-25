<?php

namespace App\Features\Games\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreasureBoxHistory extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'treasure_box_id',
        'user_id',
        'event_type',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
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
