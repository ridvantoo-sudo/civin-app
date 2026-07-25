<?php

namespace App\Features\Games\Plugins\SlotMachine\Models;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlay;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlotMachineHistory extends Model
{
    use HasUuids;

    protected $table = 'slot_machine_histories';

    protected $fillable = [
        'user_id',
        'spin_id',
        'config_id',
        'game_id',
        'play_id',
        'game_mode',
        'result',
        'bet_coins',
        'reward_coins',
        'reward_diamonds',
        'is_jackpot',
        'is_bonus',
        'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'bet_coins' => 'integer',
            'reward_coins' => 'integer',
            'reward_diamonds' => 'integer',
            'is_jackpot' => 'boolean',
            'is_bonus' => 'boolean',
            'snapshot' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function spin(): BelongsTo
    {
        return $this->belongsTo(SlotMachineSpin::class, 'spin_id');
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(SlotMachineConfig::class, 'config_id');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function play(): BelongsTo
    {
        return $this->belongsTo(GamePlay::class, 'play_id');
    }
}
