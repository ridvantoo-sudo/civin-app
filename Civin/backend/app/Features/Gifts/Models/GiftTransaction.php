<?php

namespace App\Features\Gifts\Models;

use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use Database\Factories\GiftTransactionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftTransaction extends Model
{
    /** @use HasFactory<GiftTransactionFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'sender_id', 'receiver_id', 'room_id', 'gift_id', 'quantity', 'coins', 'metadata', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'coins' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(LiveRoom::class, 'room_id');
    }

    public function gift(): BelongsTo
    {
        return $this->belongsTo(Gift::class, 'gift_id');
    }

    public function animation(): GiftAnimation
    {
        return GiftAnimation::fromGift($this->gift()->firstOrFail());
    }
}
