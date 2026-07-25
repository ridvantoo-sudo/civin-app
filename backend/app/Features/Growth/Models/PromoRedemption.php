<?php

namespace App\Features\Growth\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoRedemption extends Model
{
    use HasUuids;

    protected $fillable = [
        'promo_code_id',
        'user_id',
        'reward_type',
        'reward_value',
        'coins_awarded',
        'diamonds_awarded',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'reward_value' => 'integer',
            'coins_awarded' => 'integer',
            'diamonds_awarded' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
