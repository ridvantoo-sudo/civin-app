<?php

namespace App\Features\GameAnalytics\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only raw analytics event. Shares game_analytics_events with Games feature.
 */
class AnalyticsEvent extends Model
{
    use HasUuids;

    protected $table = 'game_analytics_events';

    public $timestamps = false;

    protected $fillable = [
        'idempotency_key',
        'user_id',
        'game_id',
        'game_slug',
        'event',
        'source',
        'category',
        'room_type',
        'room_id',
        'country_code',
        'device_platform',
        'coins_wagered',
        'coins_won',
        'coins_burned',
        'diamonds_consumed',
        'revenue',
        'payload',
        'metadata',
        'created_at',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'metadata' => 'array',
            'coins_wagered' => 'decimal:4',
            'coins_won' => 'decimal:4',
            'coins_burned' => 'decimal:4',
            'diamonds_consumed' => 'decimal:4',
            'revenue' => 'decimal:4',
            'created_at' => 'datetime',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
