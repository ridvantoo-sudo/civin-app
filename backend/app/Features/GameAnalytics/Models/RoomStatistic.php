<?php

namespace App\Features\GameAnalytics\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomStatistic extends Model
{
    use HasUuids;

    protected $table = 'game_room_statistics';

    protected $fillable = [
        'room_type', 'room_id', 'host_id', 'stat_date', 'sessions', 'unique_players',
        'peak_ccu', 'coins_wagered', 'revenue', 'gifts_volume', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'coins_wagered' => 'decimal:4',
            'revenue' => 'decimal:4',
            'gifts_volume' => 'decimal:4',
            'metadata' => 'array',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }
}
