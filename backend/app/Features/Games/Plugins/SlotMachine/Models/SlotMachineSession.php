<?php

namespace App\Features\Games\Plugins\SlotMachine\Models;

use App\Features\Games\Models\Game;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlotMachineSession extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    protected $table = 'slot_machine_sessions';

    protected $fillable = [
        'user_id',
        'config_id',
        'game_id',
        'client_session_id',
        'game_mode',
        'bet_amount',
        'auto_spin_remaining',
        'free_spins_remaining',
        'bonus_spins_remaining',
        'status',
        'metadata',
        'last_spin_at',
    ];

    protected function casts(): array
    {
        return [
            'bet_amount' => 'integer',
            'auto_spin_remaining' => 'integer',
            'free_spins_remaining' => 'integer',
            'bonus_spins_remaining' => 'integer',
            'metadata' => 'array',
            'last_spin_at' => 'datetime',
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

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function spins(): HasMany
    {
        return $this->hasMany(SlotMachineSpin::class, 'session_id');
    }
}
