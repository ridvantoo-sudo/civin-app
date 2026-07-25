<?php

namespace App\Features\Games\Plugins\SlotMachine\Models;

use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlotMachineStatistic extends Model
{
    use HasUuids;

    protected $table = 'slot_machine_statistics';

    protected $fillable = [
        'user_id',
        'config_id',
        'games_played',
        'coins_wagered',
        'coins_won',
        'diamonds_won',
        'jackpots',
        'bonus_wins',
        'free_spins_played',
        'highest_win',
        'rtp_percent',
        'average_win',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'games_played' => 'integer',
            'coins_wagered' => 'integer',
            'coins_won' => 'integer',
            'diamonds_won' => 'integer',
            'jackpots' => 'integer',
            'bonus_wins' => 'integer',
            'free_spins_played' => 'integer',
            'highest_win' => 'integer',
            'rtp_percent' => 'float',
            'average_win' => 'float',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(SlotMachineConfig::class, 'config_id');
    }
}
