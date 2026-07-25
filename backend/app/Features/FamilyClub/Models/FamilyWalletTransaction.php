<?php

namespace App\Features\FamilyClub\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyWalletTransaction extends Model
{
    use HasUuids;

    public const TYPE_CONTRIBUTE = 'CONTRIBUTE';

    public const TYPE_GIFT_RECEIVED = 'GIFT_RECEIVED';

    public const TYPE_MISSION_REWARD = 'MISSION_REWARD';

    public const CURRENCY_COINS = 'coins';

    public const CURRENCY_DIAMONDS = 'diamonds';

    public $timestamps = false;

    protected $fillable = [
        'family_id',
        'user_id',
        'type',
        'amount',
        'currency',
        'note',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
