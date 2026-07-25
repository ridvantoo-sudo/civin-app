<?php

namespace App\Features\Couple\Models;

use App\Features\Gifts\Models\Gift;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoupleGift extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'couple_id',
        'sender_id',
        'receiver_id',
        'gift_id',
        'quantity',
        'coins',
        'xp_awarded',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'coins' => 'integer',
            'xp_awarded' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function gift(): BelongsTo
    {
        return $this->belongsTo(Gift::class, 'gift_id');
    }
}
