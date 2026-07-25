<?php

namespace App\Features\SocialEngagement\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCheckIn extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'check_in_date',
        'streak_day',
        'coins_awarded',
        'diamonds_awarded',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'streak_day' => 'integer',
            'coins_awarded' => 'integer',
            'diamonds_awarded' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
